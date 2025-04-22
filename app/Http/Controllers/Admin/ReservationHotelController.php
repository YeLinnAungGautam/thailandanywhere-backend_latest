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

    // Define allowed product types
    protected $allowedProductTypes = [
        'App\Models\Hotel',
        'App\Models\EntranceTicket',
        'App\Models\PrivateVanTour'
    ];

    /**
     * Get hotel reservations grouped by CRM ID
     */
    public function getHotelReservations(Request $request)
    {
        $limit = $request->query('limit', 10);
        $page = $request->input('page', 1);
        $productType = $this->validateProductType($request->product_type);

        // Build base query
        $query = $this->buildBaseReservationQuery($request, $productType);

        // Calculate totals efficiently
        $totalProductAmount = $this->calculateTotalProductAmount($query, $request, $productType);
        $totalExpenseAmount = class_exists('BookingItemDataService') ?
            BookingItemDataService::getTotalExpenseAmount($query->clone()) : 0;

        // Get and group bookings efficiently
        $bookings = $query->orderBy('bookings.created_at', 'desc')->get();
        $results = $this->groupBookingsByCrmId($bookings, $request);

        // Apply pagination
        $paginatedData = $this->paginateResults($results, $limit, $page, $totalProductAmount, $totalExpenseAmount, $productType);

        $titlePrefix = $this->getProductTypeTitle($productType);
        return $this->success($paginatedData, $titlePrefix . ' Reservations Grouped By CRM ID');
    }

    /**
     * Get detailed reservation by booking ID
     */
    public function getHotelReservationDetail(Request $request, $id, $product_id = null)
    {
        $productType = $this->validateProductType($request->product_type);

        // Query for the specific booking by ID with necessary relations
        $booking = $this->getBookingWithItems($id, $productType, $product_id);

        if (!$booking) {
            return $this->error('No reservation found for the provided booking ID', 404);
        }

        if (!$this->userCanAccessBooking($booking)) {
            return $this->error('You do not have permission to view this booking', 403);
        }

        $totalItemsCount = DB::table('booking_items')
            ->where('booking_id', $id)
            ->count();

        // Get related bookings if any
        $relatedBookings = $this->getRelatedBookings($booking, $productType, $product_id);

        // Format response
        $result = [
            'booking' => new ReservationGroupByResource($booking),
            'total_items_count' => $totalItemsCount,
            'group_info' => $this->buildGroupInfo($booking, $relatedBookings)
        ];

        $titlePrefix = $this->getProductTypeTitle($productType);
        return $this->success($result, $titlePrefix . ' Reservation Detail');
    }

    /**
     * Get private van tour reservation details by booking ID with car booking information
     */
    public function getPrivateVanTourReservationDetail(Request $request, $id)
    {
        $productType = 'App\Models\PrivateVanTour';

        // Query for the specific booking by ID with car booking relations
        $booking = $this->getBookingWithCarDetails($id, $productType);

        if (!$booking) {
            return $this->error('No reservation found for the provided booking ID', 404);
        }

        if (!$this->userCanAccessBooking($booking)) {
            return $this->error('You do not have permission to view this booking', 403);
        }

        $totalItemsCount = DB::table('booking_items')
            ->where('booking_id', $id)
            ->where('product_type', $productType)
            ->count();

        // Get related bookings if any
        $relatedBookings = $this->getRelatedBookingsWithCarDetails($booking, $productType);

        // Process car booking details
        $this->processCarBookingDetails($booking);
        if ($relatedBookings) {
            $this->processRelatedBookingsCarDetails($relatedBookings);
        }

        // Format response
        $result = [
            'booking' => new ReservationGroupByResource($booking),
            'total_items_count' => $totalItemsCount,
            'group_info' => $this->buildGroupInfo($booking, $relatedBookings)
        ];

        return $this->success($result, 'Private Van Tour Reservation Detail');
    }

    /**
     * Copy booking items group
     */
    public function copyBookingItemsGroup(Request $request, string $bookingId, string $product_id = null)
    {
        $productType = $this->validateProductType($request->product_type);
        $booking = Booking::find($bookingId);

        if (!$booking) {
            return $this->error(null, 'Booking not found', 404);
        }

        // Load booking items with related entities
        $booking->load([
            'items' => function ($query) use ($product_id, $productType) {
                $query->where('product_type', $productType);
                if ($product_id) {
                    $query->where('product_id', $product_id);
                }
            },
            'items.product',
            'items.room',
            'items.variation',
            'customer:id,name'
        ]);

        if ($booking->items->isEmpty()) {
            $typeName = $this->getProductTypeTitle($productType, true);
            return $this->error(null, "No {$typeName} items found in this booking", 404);
        }

        // Get related items
        $relatedItems = $this->getRelatedItems($booking, $productType, $product_id);

        // Build response
        $responseData = $this->buildCopyResponse($booking, $relatedItems, $productType);

        $typeName = $this->getProductTypeTitle($productType);
        return $this->success($responseData, $typeName . ' Booking Items Group Details');
    }

    /**
     * Helper method to validate product type
     */
    private function validateProductType($productType)
    {
        return in_array($productType, $this->allowedProductTypes) ? $productType : 'App\Models\Hotel';
    }

    /**
     * Helper method to build base query for reservations
     */
    private function buildBaseReservationQuery(Request $request, $productType)
    {
        $query = Booking::query()
            ->join('booking_items', function ($join) use ($productType) {
                $join->on('bookings.id', '=', 'booking_items.booking_id')
                    ->where('booking_items.product_type', $productType);
            })
            ->select('bookings.*')
            ->distinct()
            ->whereHas('items', function($query) use ($request, $productType) {
                $query->where('product_type', $productType);

                // Apply hotel name filter
                if ($request->hotel_name) {
                    $this->applyNameFilter($query, $request->hotel_name, $productType);
                }

                // Apply invoice status filter
                if ($request->invoice_status) {
                    $this->applyInvoiceStatusFilter($query, $request->invoice_status);
                }
            })
            ->with([
                'customer:id,name,email',
                'items' => function($query) use ($productType, $request) {
                    $query->where('product_type', $productType);

                    // Apply hotel name filter
                    if ($request->hotel_name) {
                        $this->applyNameFilter($query, $request->hotel_name, $productType);
                    }

                    // Apply invoice status filter
                    if ($request->invoice_status) {
                        $this->applyInvoiceStatusFilter($query, $request->invoice_status);
                    }
                },
                'items.product:id,name',
            ]);

        // Apply date range filter
        if ($request->booking_daterange) {
            $dates = explode(',', $request->booking_daterange);
            $query->whereHas('items', function($q) use ($dates, $productType) {
                $q->where('product_type', $productType)
                  ->whereBetween('service_date', $dates);
            });
        }

        // Apply user filter
        if ($request->user_id) {
            $query->where('created_by', $request->user_id)
                  ->orWhere('past_user_id', $request->user_id);
        }

        // Apply CRM ID filter
        if ($request->crm_id) {
            $query->where('bookings.crm_id', 'LIKE', '%' . $request->crm_id . '%');
        }

        // Apply customer name filter
        if ($request->customer_name) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('customers.name', 'LIKE', '%' . $request->customer_name . '%');
            });
        }

        // Apply user role restrictions
        if (!(Auth::user()->role === 'super_admin' || Auth::user()->role === 'reservation' || Auth::user()->role === 'auditor')) {
            $query->where(function ($q) {
                $q->where('created_by', Auth::id())
                  ->orWhere('past_user_id', Auth::id());
            });
        }

        return $query;
    }

    /**
     * Apply product name filter based on product type
     */
    private function applyNameFilter($query, $name, $productType)
    {
        $table = $this->getTableNameFromProductType($productType);
        $query->whereRaw("EXISTS (SELECT 1 FROM {$table}s WHERE booking_items.product_id = {$table}s.id AND {$table}s.name LIKE ?)", ['%' . $name . '%']);
    }

    /**
     * Apply invoice status filter
     */
    private function applyInvoiceStatusFilter($query, $status)
    {
        if ($status === 'not_receive') {
            $query->where(function($subquery) {
                $subquery->whereNull('booking_items.booking_status')
                        ->orWhere('booking_items.booking_status', '')
                        ->orWhere('booking_items.booking_status', 'not_receive');
            });
        } else {
            $query->where('booking_items.booking_status', $status);
        }
    }

    /**
     * Calculate total product amount
     */
    private function calculateTotalProductAmount($query, $request, $productType)
    {
        $totalProductAmountQuery = DB::table('booking_items')
            ->whereIn('booking_id', $query->clone()->pluck('bookings.id'))
            ->where('product_type', $productType);

        // Apply hotel name filter
        if ($request->hotel_name) {
            $table = $this->getTableNameFromProductType($productType);
            $totalProductAmountQuery->whereRaw("EXISTS (SELECT 1 FROM {$table}s WHERE booking_items.product_id = {$table}s.id AND {$table}s.name LIKE ?)", ['%' . $request->hotel_name . '%']);
        }

        // Apply invoice status filter
        if ($request->invoice_status) {
            if ($request->invoice_status === 'not_receive') {
                $totalProductAmountQuery->where(function($subquery) {
                    $subquery->whereNull('booking_status')
                            ->orWhere('booking_status', '')
                            ->orWhere('booking_status', 'not_receive');
                });
            } else {
                $totalProductAmountQuery->where('booking_status', $request->invoice_status);
            }
        }

        return $totalProductAmountQuery->sum('amount');
    }

    /**
     * Group bookings by CRM ID
     */
    private function groupBookingsByCrmId($bookings, $request)
    {
        $results = collect();
        $bookingsByCrmId = $bookings->groupBy('crm_id');

        foreach ($bookingsByCrmId as $crmId => $crmBookings) {
            $processedBookings = $crmBookings->map(function($booking) {
                $booking->groupedItems = $booking->items->groupBy('product_id');
                return $booking;
            });

            $latestServiceDate = $crmBookings->max(function ($booking) {
                return $booking->items->max('service_date');
            });

            // Calculate payment statuses
            $hasFullyPaid = $crmBookings->flatMap->items->contains('payment_status', 'fully_paid');
            $hasNotPaid = $crmBookings->flatMap->items->contains('payment_status', 'not_paid');

            $expenseStatus = 'fully_paid';
            if ($hasFullyPaid && $hasNotPaid) {
                $expenseStatus = 'partially_paid';
            } elseif ($hasNotPaid && !$hasFullyPaid) {
                $expenseStatus = 'not_paid';
            }

            $recentBooking = $crmBookings->sortByDesc('created_at')->first();
            $customerPaymentStatus = $recentBooking->payment_status ?? 'not_paid';

            $totalAmount = $crmBookings->sum(function ($booking) {
                return $booking->items->sum('amount');
            });

            $results->push([
                'crm_id' => $crmId,
                'latest_service_date' => $latestServiceDate,
                'total_bookings' => $crmBookings->count(),
                'total_amount' => $totalAmount,
                'customer_payment_status' => $customerPaymentStatus,
                'expense_status' => $expenseStatus,
                'bookings' => BookingGroupResource::collection($processedBookings),
            ]);
        }

        // Apply filters
        if ($request->expense_status) {
            $results = $results->filter(function ($group) use ($request) {
                return $group['expense_status'] === $request->expense_status;
            });
        }

        if ($request->customer_payment_status) {
            $results = $results->filter(function ($group) use ($request) {
                return $group['customer_payment_status'] === $request->customer_payment_status;
            });
        }

        // Apply sorting
        $orderBy = $request->order_by ?? 'service_date';
        $orderDirection = $request->order_direction ?? 'desc';

        $results = $this->sortResults($results, $orderBy, $orderDirection);

        return $results->values();
    }

    /**
     * Sort results by specified field
     */
    private function sortResults($results, $orderBy, $orderDirection)
    {
        $paymentStatusPriority = [
            'not_paid' => 3,
            'partially_paid' => 2,
            'fully_paid' => 1
        ];

        $sortMethod = $orderDirection === 'desc' ? 'sortByDesc' : 'sortBy';

        if ($orderBy === 'customer_payment_status') {
            return $results->$sortMethod(function ($group) use ($paymentStatusPriority) {
                $status = $group['customer_payment_status'] ?? 'not_paid';
                return $paymentStatusPriority[$status] ?? 0;
            });
        } elseif ($orderBy === 'expense_status') {
            return $results->$sortMethod(function ($group) use ($paymentStatusPriority) {
                $status = $group['expense_status'] ?? 'not_paid';
                return $paymentStatusPriority[$status] ?? 0;
            });
        } elseif ($orderBy === 'service_date' || $orderBy === '') {
            return $results->$sortMethod('latest_service_date');
        }

        return $results->sortByDesc('latest_service_date');
    }

    /**
     * Paginate results
     */
    private function paginateResults($results, $limit, $page, $totalProductAmount, $totalExpenseAmount, $productType)
    {
        $total = $results->count();
        $perPage = $limit;
        $offset = ($page - 1) * $perPage;
        $paginatedResults = $results->slice($offset, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedResults,
            $total,
            $perPage,
            $page,
            ['path' => \Illuminate\Support\Facades\Request::url()]
        );

        $response = (new \Illuminate\Http\Resources\Json\AnonymousResourceCollection(
            $paginator,
            \App\Http\Resources\HotelGroupResource::class
        ))->additional([
            'meta' => [
                'total_page' => (int)ceil($total / $perPage),
                'total_amount' => $totalProductAmount,
                'total_expense_amount' => $totalExpenseAmount,
                'product_type' => $productType
            ],
        ]);

        return $response->response()->getData();
    }

    /**
     * Get booking with items
     */
    private function getBookingWithItems($id, $productType, $product_id = null)
    {
        return Booking::with([
            'customer',
            'items' => function($query) use ($product_id, $productType) {
                $query->where('product_type', $productType);
                if ($product_id) {
                    $query->where('product_id', $product_id);
                }
            },
            'items.product',
        ])
        ->where('id', $id)
        ->first();
    }

    /**
     * Get booking with car details
     */
    private function getBookingWithCarDetails($id, $productType)
    {
        return Booking::with([
            'customer',
            'items' => function($query) use ($productType) {
                $query->where('product_type', $productType);
            },
            'items.product',
            'items.variation',
            'items.reservationCarInfo',
            'items.reservationCarInfo.supplier',
            'items.reservationCarInfo.driverInfo',
            'items.reservationCarInfo.driverInfo.driver',
            'items.reservationInfo:id,booking_item_id,pickup_location,pickup_time',
        ])
        ->where('id', $id)
        ->first();
    }

    /**
     * Check if user can access booking
     */
    private function userCanAccessBooking($booking)
    {
        $isAdminRole = Auth::user()->role === 'super_admin' ||
                       Auth::user()->role === 'reservation' ||
                       Auth::user()->role === 'auditor';

        if ($isAdminRole) {
            return true;
        }

        return $booking->created_by === Auth::id() || $booking->past_user_id === Auth::id();
    }

    /**
     * Get related bookings for a booking
     */
    private function getRelatedBookings($booking, $productType, $product_id = null)
    {
        if (!$booking->crm_id) {
            return null;
        }

        return Booking::with([
            'customer',
            'items' => function($query) use ($product_id, $productType) {
                $query->where('product_type', $productType);
                if ($product_id) {
                    $query->where('product_id', $product_id);
                }
            },
            'items.product',
        ])
        ->where('crm_id', $booking->crm_id)
        ->where('id', '!=', $booking->id)
        ->get();
    }

    /**
     * Get related bookings with car details
     */
    private function getRelatedBookingsWithCarDetails($booking, $productType)
    {
        if (!$booking->crm_id) {
            return null;
        }

        return Booking::with([
            'customer',
            'items' => function($query) use ($productType) {
                $query->where('product_type', $productType);
            },
            'items.product',
            'items.variation',
            'items.reservationCarInfo',
            'items.reservationCarInfo.supplier',
            'items.reservationCarInfo.driverInfo',
            'items.reservationCarInfo.driverInfo.driver',
            'items.reservationInfo:id,booking_item_id,pickup_location,pickup_time',
        ])
        ->where('crm_id', $booking->crm_id)
        ->where('id', '!=', $booking->id)
        ->get();
    }

    /**
     * Build group info for response
     */
    private function buildGroupInfo($booking, $relatedBookings)
    {
        if (!$relatedBookings || $relatedBookings->count() === 0) {
            return null;
        }

        $allBookings = collect([$booking])->merge($relatedBookings);

        return [
            'crm_id' => $booking->crm_id,
            'total_bookings' => $allBookings->count(),
            'total_amount' => $allBookings->sum(function ($b) {
                return $b->items->sum('amount');
            }),
            'latest_service_date' => $allBookings->max(function ($b) {
                return $b->items->max('service_date');
            }),
            'related_bookings' => BookingResource::collection($relatedBookings),
        ];
    }

    /**
     * Process car booking details
     */
    private function processCarBookingDetails($booking)
    {
        foreach ($booking->items as $item) {
            $this->addCarBookingDetailsToItem($item);
        }
    }

    /**
     * Process related bookings car details
     */
    private function processRelatedBookingsCarDetails($relatedBookings)
    {
        foreach ($relatedBookings as $relatedBooking) {
            foreach ($relatedBooking->items as $item) {
                $this->addCarBookingDetailsToItem($item);
            }
        }
    }

    /**
     * Add car booking details to item
     */
    private function addCarBookingDetailsToItem($item)
    {
        $carInfo = null;
        $driverInfo = null;
        $pickupInfo = null;
        $isAssigned = false;
        $isComplete = false;

        // Check if car is assigned
        if ($item->reservationCarInfo) {
            $isAssigned = true;
            $carInfo = [
                'id' => $item->reservationCarInfo->id,
                'supplier_id' => $item->reservationCarInfo->supplier_id ?? null,
                'supplier_name' => $item->reservationCarInfo->supplier->name ?? 'N/A',
            ];

            // Check if driver info exists
            if ($item->reservationCarInfo->driverInfo &&
                $item->reservationCarInfo->driverInfo->driver) {
                $driverInfo = [
                    'id' => $item->reservationCarInfo->driverInfo->driver->id ?? null,
                    'name' => $item->reservationCarInfo->driverInfo->driver->name ?? 'N/A',
                    'contact' => 'N/A', // Safe default since we don't have driver->contact
                ];
            }
        }

        // Get pickup information
        if ($item->reservationInfo) {
            $pickupInfo = [
                'id' => $item->reservationInfo->id,
                'location' => $item->reservationInfo->pickup_location ?? null,
                'time' => $item->reservationInfo->pickup_time ?? null,
            ];
        }

        // Check if booking is complete
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

        // Add details to item
        $item->car_booking_details = [
            'is_assigned' => $isAssigned,
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

    /**
     * Get related items for copying
     */
    private function getRelatedItems($booking, $productType, $product_id)
    {
        $relatedItems = [];

        if ($booking->crm_id) {
            $relatedBookings = Booking::with([
                'items' => function ($query) use ($product_id, $productType) {
                    $query->where('product_type', $productType);
                    if ($product_id) {
                        $query->where('product_id', $product_id);
                    }
                },
                'items.product',
                'items.room',
                'items.variation'
            ])
            ->where('crm_id', $booking->crm_id)
            ->where('id', '!=', $booking->id)
            ->get();

            foreach ($relatedBookings as $relatedBooking) {
                foreach ($relatedBooking->items as $item) {
                    $relatedItems[] = new BookingItemDetailResource($item);
                }
            }
        }

        return $relatedItems;
    }

    /**
     * Build response for copy booking items
     */
    private function buildCopyResponse($booking, $relatedItems, $productType)
    {
        $responseData = [
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
        ];

        // Add summary based on product type
        $responseData['summary'] = $this->calculateSummary($booking, $productType);

        return $responseData;
    }

    /**
     * Calculate summary based on product type
     */
    private function calculateSummary($booking, $productType)
    {
        $summary = [
            'total_amount' => $booking->items->sum('amount'),
            'total_cost' => $booking->items->sum('total_cost_price')
        ];

        if ($productType == 'App\Models\Hotel') {
            $summary['total_rooms'] = $booking->items->sum('quantity');
            $summary['total_nights'] = $booking->items->sum(function ($item) {
                if ($item->checkin_date && $item->checkout_date) {
                    return (int) Carbon::parse($item->checkin_date)->diff(Carbon::parse($item->checkout_date))->format("%a") * $item->quantity;
                }
                return 0;
            });
        } elseif ($productType == 'App\Models\EntranceTicket') {
            $summary['total_tickets'] = $booking->items->sum('quantity');
        } elseif ($productType == 'App\Models\PrivateVanTour') {
            $summary['total_tours'] = $booking->items->sum('quantity');
        }

        return $summary;
    }

    /**
     * Helper method to get user-friendly product type title
     */
    private function getProductTypeTitle($productType, $lowercase = false)
    {
        $titles = [
            'App\Models\EntranceTicket' => 'Entrance Ticket',
            'App\Models\PrivateVanTour' => 'Private Van Tour',
            'App\Models\Hotel' => 'Hotel'
        ];

        $title = $titles[$productType] ?? 'Hotel';
        return $lowercase ? strtolower($title) : $title;
    }

    /**
     * Get table name from product type
     */
    private function getTableNameFromProductType($productType)
    {
        $tables = [
            'App\Models\Hotel' => 'hotel',
            'App\Models\EntranceTicket' => 'entrance_ticket',
            'App\Models\PrivateVanTour' => 'private_van_tour'
        ];

        return $tables[$productType] ?? 'hotel';
    }
}
