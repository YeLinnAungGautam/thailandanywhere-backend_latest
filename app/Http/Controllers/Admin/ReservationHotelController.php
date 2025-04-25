<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingGroupResource;
use App\Http\Resources\BookingItemDetailResource;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ReservationGroupByResource;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\BookingItemDataService;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReservationHotelController extends Controller
{
    use ImageManager, HttpResponses;

    protected $allowedProductTypes = [
        'App\Models\Hotel',
        'App\Models\EntranceTicket',
        'App\Models\PrivateVanTour'
    ];

    protected $productTypeTitles = [
        'App\Models\Hotel' => 'Hotel',
        'App\Models\EntranceTicket' => 'Entrance Ticket',
        'App\Models\PrivateVanTour' => 'Private Van Tour'
    ];

    protected $productTypeTables = [
        'App\Models\Hotel' => 'hotel',
        'App\Models\EntranceTicket' => 'entrance_ticket',
        'App\Models\PrivateVanTour' => 'private_van_tour'
    ];

    /**
     * Get hotel reservations grouped by CRM ID
     */
    public function getHotelReservations(Request $request)
    {
        $productType = $this->validateProductType($request->product_type);
        $this->normalizeExpenseStatus($request);

        // First, get booking items with filters
        $filteredBookingItems = $this->getFilteredBookingItems($request, $productType);

        // Calculate totals based on filtered items
        $totals = [
            'product_amount' => $filteredBookingItems->sum('amount'),
            'expense_amount' => class_exists('BookingItemDataService') ?
                BookingItemDataService::getTotalExpenseAmount($this->buildBookingIdsQuery($filteredBookingItems)) : 0
        ];

        // Get bookings associated with filtered items
        $bookingIds = $filteredBookingItems->pluck('booking_id')->unique();
        $bookings = $this->getBookingsByIds($bookingIds, $productType);

        // Group booking items by booking and then group bookings by CRM ID
        $groupedResults = $this->groupBookingsByCrmId($bookings, $filteredBookingItems);

        $response = $this->buildPaginatedResponse(
            $groupedResults,
            $request->query('limit', 10),
            $request->input('page', 1),
            $totals,
            $productType
        );

        return $this->success($response, $this->getProductTypeTitle($productType) . ' Reservations Grouped By CRM ID');
    }

    /**
     * Get filtered booking items
     */
    protected function getFilteredBookingItems(Request $request, $productType)
    {
        $query = BookingItem::where('product_type', $productType);

        // Apply item-specific filters
        if ($request->hotel_name) {
            $this->applyNameFilter($query, $request->hotel_name, $productType);
        }

        if ($request->expense_status_item) {
            $query->where('payment_status', $request->expense_status_item);
        }

        if ($request->invoice_status) {
            $this->applyInvoiceStatusFilter($query, $request->invoice_status);
        }

        // Get distinct booking_ids that meet booking-level filters first
        $filteredBookingIds = $this->getFilteredBookingIds($request);

        // Special handling for PrivateVanTour with service date filtering
        if ($request->booking_daterange && $productType === 'App\Models\PrivateVanTour') {
            $dates = explode(',', $request->booking_daterange);
            $dates = array_map('trim', $dates);

            // For PrivateVanTour, if at least one item in a booking matches the date,
            // we want to include ALL items from that booking

            // Single date case (exact match)
            if (count($dates) == 1 || (count($dates) == 2 && $dates[0] == $dates[1])) {
                $exactDate = $dates[0];

                // Step 1: Find all booking IDs that have at least one item with the matching service date
                $matchingBookingIds = BookingItem::where('product_type', $productType)
                    ->where('service_date', $exactDate);

                // Apply booking-level filters if they exist
                if ($filteredBookingIds !== null) {
                    $matchingBookingIds->whereIn('booking_id', $filteredBookingIds);
                }

                // Get the IDs of bookings with matching items
                $matchingBookingIds = $matchingBookingIds->pluck('booking_id')->unique();

                // Step 2: Now get ALL PrivateVanTour items from those bookings
                if ($matchingBookingIds->isNotEmpty()) {
                    $query->whereIn('booking_id', $matchingBookingIds);
                } else {
                    // If no bookings match the date, return an empty result
                    $query->where('id', 0); // This ensures no results
                }
            }
            // Date range case
            else {
                // Step 1: Find all booking IDs that have at least one item with service_date in the range
                $matchingBookingIds = BookingItem::where('product_type', $productType)
                    ->whereBetween('service_date', $dates);

                // Apply booking-level filters if they exist
                if ($filteredBookingIds !== null) {
                    $matchingBookingIds->whereIn('booking_id', $filteredBookingIds);
                }

                // Get the IDs of bookings with matching items
                $matchingBookingIds = $matchingBookingIds->pluck('booking_id')->unique();

                // Step 2: Now get ALL PrivateVanTour items from those bookings
                if ($matchingBookingIds->isNotEmpty()) {
                    $query->whereIn('booking_id', $matchingBookingIds);
                } else {
                    // If no bookings match the date range, return an empty result
                    $query->where('id', 0); // This ensures no results
                }
            }
        }
        // For all other product types or no date filter
        else if ($request->booking_daterange) {
            // For other product types, apply date range filter directly to items
            $dates = explode(',', $request->booking_daterange);
            $dates = array_map('trim', $dates);
            $query->whereBetween('service_date', $dates);

            // Apply booking ID filters if they exist
            if ($filteredBookingIds !== null) {
                $query->whereIn('booking_id', $filteredBookingIds);
            }
        }
        // No date filter but has booking filters
        else if ($filteredBookingIds !== null) {
            $query->whereIn('booking_id', $filteredBookingIds);
        }

        // Fetch items with product relation
        return $query->with('product:id,name')->get();
    }

    /**
     * Get booking IDs that match booking-level filters
     */
    protected function getFilteredBookingIds(Request $request)
    {
        if (!$this->hasBookingFilters($request)) {
            return null;
        }

        $query = Booking::query();

        // Payment status
        if ($request->customer_payment_status) {
            $query->where('payment_status', $request->customer_payment_status);
        }

        // User filter
        if ($request->user_id) {
            $query->where(function($q) use ($request) {
                $q->where('created_by', $request->user_id)
                  ->orWhere('past_user_id', $request->user_id);
            });
        }

        // CRM ID filter
        if ($request->crm_id) {
            $query->where('bookings.crm_id', 'LIKE', '%' . $request->crm_id . '%');
        }

        // Customer name filter
        if ($request->customer_name) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('customers.name', 'LIKE', '%' . $request->customer_name . '%');
            });
        }

        // User role restrictions
        if (!in_array(Auth::user()->role, ['super_admin', 'reservation', 'auditor'])) {
            $query->where(function ($q) {
                $q->where('created_by', Auth::id())
                  ->orWhere('past_user_id', Auth::id());
            });
        }

        return $query->pluck('id');
    }

    /**
     * Check if request has any booking-level filters
     */
    protected function hasBookingFilters(Request $request)
    {
        return $request->customer_payment_status ||
               $request->user_id ||
               $request->crm_id ||
               $request->customer_name ||
               !in_array(Auth::user()->role, ['super_admin', 'reservation', 'auditor']);
    }

    /**
     * Build query for booking IDs
     */
    protected function buildBookingIdsQuery($filteredBookingItems)
    {
        return Booking::whereIn('id', $filteredBookingItems->pluck('booking_id')->unique());
    }

    /**
     * Get bookings by IDs with required relations
     */
    protected function getBookingsByIds($bookingIds, $productType)
    {
        return Booking::with([
            'customer:id,name,email',
            'items' => function($query) use ($productType) {
                $query->where('product_type', $productType);
            },
            'items.product:id,name',
        ])->whereIn('id', $bookingIds)
          ->orderBy('created_at', 'desc')
          ->get();
    }

    /**
     * Group bookings by CRM ID
     */
    protected function groupBookingsByCrmId($bookings, $filteredBookingItems)
    {
        // Create a lookup map for filtered items by booking_id
        $filteredItemsByBooking = $filteredBookingItems->groupBy('booking_id');

        // Attach filtered items to each booking
        $bookings->each(function($booking) use ($filteredItemsByBooking) {
            if (isset($filteredItemsByBooking[$booking->id])) {
                $booking->filteredItems = $filteredItemsByBooking[$booking->id];
                $booking->groupedItems = $booking->filteredItems->groupBy('product_id');
            } else {
                $booking->filteredItems = collect();
            }

            // Store all items for PrivateVanTour bookings for total amount calculation
            if ($booking->items->isNotEmpty() && $booking->items->first()->product_type === 'App\Models\PrivateVanTour') {
                $booking->allItems = $booking->items;
            } else {
                // For other product types, use filteredItems for calculations
                $booking->allItems = $booking->filteredItems;
            }
        });

        // Filter out bookings with no filtered items
        $bookingsWithItems = $bookings->filter(function($booking) {
            return $booking->filteredItems->isNotEmpty();
        });

        // Group by CRM ID
        return $bookingsWithItems->groupBy('crm_id')
            ->map(function($crmBookings) {
                $recentBooking = $crmBookings->sortByDesc('created_at')->first();
                $expenseStatus = $this->calculateGroupExpenseStatus($crmBookings);

                // Get the latest service date across all filtered items
                $latestServiceDate = $crmBookings->max(fn($b) => $b->filteredItems->max('service_date'));

                // For amount calculation, use allItems which depends on product type
                $totalAmount = $crmBookings->sum(fn($b) => $b->allItems->sum('amount'));

                return [
                    'crm_id' => $recentBooking->crm_id,
                    'latest_service_date' => $latestServiceDate,
                    'total_bookings' => $crmBookings->count(),
                    'total_amount' => $totalAmount,
                    'customer_payment_status' => $recentBooking->payment_status ?? 'not_paid',
                    'expense_status' => $expenseStatus,
                    'bookings' => BookingGroupResource::collection($crmBookings),
                ];
            })
            ->values();
    }

    /**
     * Get hotel reservation detail
     */
    public function getHotelReservationDetail(Request $request, $id, $product_id = null)
    {
        $productType = $this->validateProductType($request->product_type);
        $booking = $this->getBookingWithItems($id, $productType, $product_id);

        if (!$booking) {
            return $this->error('No reservation found for the provided booking ID', 404);
        }

        if (!$this->userCanAccessBooking($booking)) {
            return $this->error('You do not have permission to view this booking', 403);
        }

        $response = $this->buildDetailResponse($booking, $productType, $product_id);
        return $this->success($response, $this->getProductTypeTitle($productType) . ' Reservation Detail');
    }

    /**
     * Get private van tour reservation details
     */
    public function getPrivateVanTourReservationDetail(Request $request, $id)
    {
        $productType = 'App\Models\PrivateVanTour';

        // Get the booking with all items first
        $booking = $this->getBookingWithCarDetails($id, $productType);

        if (!$booking) {
            return $this->error('No reservation found for the provided booking ID', 404);
        }

        if (!$this->userCanAccessBooking($booking)) {
            return $this->error('You do not have permission to view this booking', 403);
        }

        // For PrivateVanTour with date filter, check if ANY item matches the date criteria
        $hasMatchingItems = true;
        if ($request->booking_daterange) {
            $dates = explode(',', $request->booking_daterange);
            $dates = array_map('trim', $dates);

            // Single date case
            if (count($dates) == 1 || (count($dates) == 2 && $dates[0] == $dates[1])) {
                $exactDate = $dates[0];
                $hasMatchingItems = $booking->items->contains(function($item) use ($exactDate) {
                    return $item->service_date == $exactDate;
                });
            }
            // Date range case
            else if (count($dates) == 2) {
                $startDate = $dates[0];
                $endDate = $dates[1];
                $hasMatchingItems = $booking->items->contains(function($item) use ($startDate, $endDate) {
                    if (!$item->service_date) return false;
                    return ($item->service_date >= $startDate && $item->service_date <= $endDate);
                });
            }
        }

        // If no items match the date criteria, return an error
        if (!$hasMatchingItems) {
            return $this->error('No reservation found for the specified service date', 404);
        }

        // Process car booking details
        $this->processCarBookingDetails($booking);

        // Get related bookings with the same CRM ID
        $relatedBookings = $this->getRelatedBookingsWithCarDetails($booking, $productType);

        // Process related bookings if they exist
        if ($relatedBookings && $relatedBookings->isNotEmpty()) {
            $this->processRelatedBookingsCarDetails($relatedBookings);

            // Filter related bookings by the same date criteria if date filter is provided
            if ($request->booking_daterange) {
                $dates = explode(',', $request->booking_daterange);
                $dates = array_map('trim', $dates);

                $relatedBookings = $relatedBookings->filter(function($relatedBooking) use ($dates) {
                    // Single date case
                    if (count($dates) == 1 || (count($dates) == 2 && $dates[0] == $dates[1])) {
                        $exactDate = $dates[0];
                        return $relatedBooking->items->contains(function($item) use ($exactDate) {
                            return $item->service_date == $exactDate;
                        });
                    }
                    // Date range case
                    else if (count($dates) == 2) {
                        $startDate = $dates[0];
                        $endDate = $dates[1];
                        return $relatedBooking->items->contains(function($item) use ($startDate, $endDate) {
                            if (!$item->service_date) return false;
                            return ($item->service_date >= $startDate && $item->service_date <= $endDate);
                        });
                    }

                    // Default case (should not happen)
                    return true;
                })->values(); // Reset array keys
            }
        }

        // Build and return the response
        $response = $this->buildDetailResponse($booking, $productType, null, $relatedBookings);
        return $this->success($response, 'Private Van Tour Reservation Detail');
    }

    /**
     * Copy booking items group
     */
    public function copyBookingItemsGroup(Request $request, string $bookingId, string $product_id = null)
    {
        $productType = $this->validateProductType($request->product_type);
        $booking = Booking::with($this->getCopyItemRelations($product_id, $productType))
            ->find($bookingId);

        if (!$booking) {
            return $this->error(null, 'Booking not found', 404);
        }

        if ($booking->items->isEmpty()) {
            return $this->error(null, "No {$this->getProductTypeTitle($productType, true)} items found", 404);
        }

        $responseData = $this->buildCopyResponse($booking, $productType, $product_id);
        return $this->success($responseData, $this->getProductTypeTitle($productType) . ' Booking Items Group Details');
    }

    /****************************************************************
     * Helper Methods
     ***************************************************************/

    /**
     * Process and filter dates from request
     */
    protected function processDatesFromRequest($request)
    {
        if (!$request->booking_daterange) {
            return null;
        }

        try {
            $dates = explode(',', $request->booking_daterange);

            // Clean the dates
            $dates = array_map('trim', $dates);

            // Check if it's a single date or identical dates
            $isSingleDate = count($dates) == 1 || (count($dates) == 2 && $dates[0] === $dates[1]);

            return [
                'dates' => $dates,
                'isSingleDate' => $isSingleDate,
                'exactDate' => $isSingleDate ? $dates[0] : null
            ];
        } catch (\Exception $e) {
            // Return null if any error occurs
            return null;
        }
    }

    protected function validateProductType($productType)
    {
        return in_array($productType, $this->allowedProductTypes) ? $productType : 'App\Models\Hotel';
    }

    protected function normalizeExpenseStatus(Request $request)
    {
        if ($request->has('expense_status')) {
            $request->expense_status_crm = $request->expense_status;
            $request->expense_status_item = $request->expense_status_item ?? $request->expense_status;
        }
    }

    protected function applyNameFilter($query, $name, $productType)
    {
        $table = $this->productTypeTables[$productType] ?? 'hotel';
        $query->whereRaw("EXISTS (SELECT 1 FROM {$table}s WHERE booking_items.product_id = {$table}s.id AND {$table}s.name LIKE ?)", ['%' . $name . '%']);
    }

    protected function applyInvoiceStatusFilter($query, $status)
    {
        if ($status === 'not_receive') {
            $query->where(function($subquery) {
                $subquery->whereNull('booking_status')
                        ->orWhere('booking_status', '')
                        ->orWhere('booking_status', 'not_receive');
            });
        } else {
            $query->where('booking_status', $status);
        }
    }

    protected function calculateGroupExpenseStatus($crmBookings)
    {
        $hasFullyPaid = $crmBookings->flatMap(function($booking) {
            return $booking->filteredItems ?? $booking->items;
        })->contains('payment_status', 'fully_paid');

        $hasNotPaid = $crmBookings->flatMap(function($booking) {
            return $booking->filteredItems ?? $booking->items;
        })->contains('payment_status', 'not_paid');

        if ($hasFullyPaid && $hasNotPaid) return 'partially_paid';
        if ($hasNotPaid && !$hasFullyPaid) return 'not_paid';
        return 'fully_paid';
    }

    protected function buildPaginatedResponse($results, $limit, $page, $totals, $productType)
    {
        $total = count($results);
        $perPage = $limit;
        $offset = ($page - 1) * $perPage;

        // Convert Collection to array if needed
        $resultsArray = $results instanceof \Illuminate\Support\Collection ? $results->toArray() : $results;
        $paginatedResults = array_slice($resultsArray, $offset, $perPage);

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedResults,
            $total,
            $perPage,
            $page,
            ['path' => \Illuminate\Support\Facades\Request::url()]
        );

        return (new \Illuminate\Http\Resources\Json\AnonymousResourceCollection(
            $paginator,
            \App\Http\Resources\HotelGroupResource::class
        ))->additional([
            'meta' => [
                'total_page' => (int)ceil($total / $perPage),
                'total_amount' => $totals['product_amount'],
                'total_expense_amount' => $totals['expense_amount'],
                'product_type' => $productType
            ],
        ])->response()->getData();
    }

    protected function getBookingWithItems($id, $productType, $product_id = null)
    {
        return Booking::with([
            'customer',
            'items' => function($query) use ($product_id, $productType) {
                $query->where('product_type', $productType);
                if ($product_id) $query->where('product_id', $product_id);
            },
            'items.product',
        ])->find($id);
    }

    protected function getBookingWithCarDetails($id, $productType)
    {
        return Booking::with([
            'customer',
            'items' => fn($q) => $q->where('product_type', $productType),
            'items.product',
            'items.variation',
            'items.reservationCarInfo',
            'items.reservationCarInfo.supplier',
            'items.reservationCarInfo.driverInfo',
            'items.reservationCarInfo.driverInfo.driver',
            'items.reservationInfo:id,booking_item_id,pickup_location,pickup_time',
        ])->find($id);
    }

    protected function userCanAccessBooking($booking)
    {
        return in_array(Auth::user()->role, ['super_admin', 'reservation', 'auditor']) ||
               $booking->created_by === Auth::id() ||
               $booking->past_user_id === Auth::id();
    }

    protected function getRelatedBookings($booking, $productType, $product_id = null)
    {
        if (!$booking->crm_id) return null;

        return Booking::with([
            'customer',
            'items' => function($q) use ($product_id, $productType) {
                $q->where('product_type', $productType);
                if ($product_id) $q->where('product_id', $product_id);
            },
            'items.product',
        ])->where('crm_id', $booking->crm_id)
          ->where('id', '!=', $booking->id)
          ->get();
    }

    protected function getRelatedBookingsWithCarDetails($booking, $productType)
    {
        if (!$booking->crm_id) return null;

        return Booking::with([
            'customer',
            'items' => fn($q) => $q->where('product_type', $productType),
            'items.product',
            'items.variation',
            'items.reservationCarInfo',
            'items.reservationCarInfo.supplier',
            'items.reservationCarInfo.driverInfo',
            'items.reservationCarInfo.driverInfo.driver',
            'items.reservationInfo:id,booking_item_id,pickup_location,pickup_time',
        ])->where('crm_id', $booking->crm_id)
          ->where('id', '!=', $booking->id)
          ->get();
    }

    protected function buildDetailResponse($booking, $productType, $product_id = null, $relatedBookings = null)
    {
        $relatedBookings = $relatedBookings ?? $this->getRelatedBookings($booking, $productType, $product_id);
        $totalItemsCount = BookingItem::where('booking_id', $booking->id)
            ->when($productType, fn($q) => $q->where('product_type', $productType))
            ->count();

        return [
            'booking' => new ReservationGroupByResource($booking),
            'total_items_count' => $totalItemsCount,
            'group_info' => $this->buildGroupInfo($booking, $relatedBookings)
        ];
    }

    protected function buildGroupInfo($booking, $relatedBookings)
    {
        if (!$relatedBookings || $relatedBookings->isEmpty()) return null;

        $allBookings = collect([$booking])->merge($relatedBookings);

        return [
            'crm_id' => $booking->crm_id,
            'total_bookings' => $allBookings->count(),
            'total_amount' => $allBookings->sum(fn($b) => $b->items->sum('amount')),
            'latest_service_date' => $allBookings->max(fn($b) => $b->items->max('service_date')),
            'related_bookings' => BookingResource::collection($relatedBookings),
        ];
    }

    protected function processCarBookingDetails($booking)
    {
        $booking->items->each(fn($item) => $this->addCarBookingDetailsToItem($item));
    }

    protected function processRelatedBookingsCarDetails($relatedBookings)
    {
        $relatedBookings->each(function($booking) {
            $booking->items->each(fn($item) => $this->addCarBookingDetailsToItem($item));
        });
    }

    protected function addCarBookingDetailsToItem($item)
    {
        $carInfo = $item->reservationCarInfo ? [
            'id' => $item->reservationCarInfo->id,
            'supplier_id' => $item->reservationCarInfo->supplier_id ?? null,
            'supplier_name' => $item->reservationCarInfo->supplier->name ?? 'N/A',
        ] : null;

        $driverInfo = ($item->reservationCarInfo->driverInfo ?? false) && ($item->reservationCarInfo->driverInfo->driver ?? false) ? [
            'id' => $item->reservationCarInfo->driverInfo->driver->id ?? null,
            'name' => $item->reservationCarInfo->driverInfo->driver->name ?? 'N/A',
            'contact' => 'N/A',
        ] : null;

        $pickupInfo = $item->reservationInfo ? [
            'id' => $item->reservationInfo->id,
            'location' => $item->reservationInfo->pickup_location ?? null,
            'time' => $item->reservationInfo->pickup_time ?? null,
        ] : null;

        $isComplete = !(
            is_null($item->reservationCarInfo) ||
            is_null($item->reservationCarInfo->supplier ?? null) ||
            is_null($item->reservationCarInfo->driverInfo ?? null) ||
            is_null($item->reservationCarInfo->driverInfo->driver ?? null) ||
            is_null($item->cost_price) ||
            is_null($item->total_cost_price) ||
            is_null($item->pickup_time) ||
            is_null($item->route_plan) ||
            is_null($item->special_request) ||
            ($item->is_driver_collect && is_null($item->extra_collect_amount))
        );

        $item->car_booking_details = [
            'is_assigned' => (bool)$item->reservationCarInfo,
            'is_complete' => $isComplete,
            'car_info' => $carInfo,
            'driver_info' => $driverInfo,
            'pickup_info' => $pickupInfo,
            'route_plan' => $item->route_plan ?? null,
            'special_request' => $item->special_request ?? null,
            'is_driver_collect' => $item->is_driver_collect ?? false,
            'extra_collect_amount' => $item->extra_collect_amount ?? null,
        ];
    }

    protected function getCopyItemRelations($product_id, $productType)
    {
        return [
            'items' => function ($query) use ($product_id, $productType) {
                $query->where('product_type', $productType);
                if ($product_id) $query->where('product_id', $product_id);
            },
            'items.product',
            'items.room',
            'items.variation',
            'customer:id,name'
        ];
    }

    protected function buildCopyResponse($booking, $productType, $product_id = null)
    {
        $relatedItems = $booking->crm_id ?
            $this->getRelatedItems($booking, $productType, $product_id) : [];

        return [
            'booking_id' => $booking->id,
            'crm_id' => $booking->crm_id,
            'customer_name' => $booking->customer->name ?? '-',
            'booking_date' => $booking->booking_date,
            'payment_status' => $booking->payment_status,
            'balance_due' => $booking->balance_due,
            'selling_price' => $booking->sub_total,
            'items' => BookingItemDetailResource::collection($booking->items),
            'related_items' => $relatedItems,
            'product_type' => $productType,
            'summary' => $this->calculateSummary($booking, $productType)
        ];
    }

    protected function calculateSummary($booking, $productType)
    {
        $summary = [
            'total_amount' => $booking->items->sum('amount'),
            'total_cost' => $booking->items->sum('total_cost_price')
        ];

        switch ($productType) {
            case 'App\Models\Hotel':
                $summary['total_rooms'] = $booking->items->sum('quantity');
                $summary['total_nights'] = $booking->items->sum(function ($item) {
                    if ($item->checkin_date && $item->checkout_date) {
                        return Carbon::parse($item->checkin_date)
                            ->diffInDays(Carbon::parse($item->checkout_date)) * $item->quantity;
                    }
                    return 0;
                });
                break;
            case 'App\Models\EntranceTicket':
                $summary['total_tickets'] = $booking->items->sum('quantity');
                break;
            case 'App\Models\PrivateVanTour':
                $summary['total_tours'] = $booking->items->sum('quantity');
                break;
        }

        return $summary;
    }

    protected function getProductTypeTitle($productType, $lowercase = false)
    {
        $title = $this->productTypeTitles[$productType] ?? 'Hotel';
        return $lowercase ? strtolower($title) : $title;
    }
}
