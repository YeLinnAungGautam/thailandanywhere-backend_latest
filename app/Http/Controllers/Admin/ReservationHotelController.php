<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingGroupResource;
use App\Http\Resources\BookingItemDetailResource;
use App\Http\Resources\BookingItemResource;
use App\Http\Resources\BookingReceiptResource;
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
    use ImageManager;
    use HttpResponses;

    // Define allowed product types
    protected $allowedProductTypes = [
        'App\Models\Hotel',
        'App\Models\EntranceTicket',
        'App\Models\PrivateVanTour'
    ];

    /**
     * Get hotel reservations grouped by CRM ID
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getHotelReservations(Request $request)
    {
        $limit = $request->query('limit', 10);
        $page = $request->input('page', 1);

        // Get product type from request or use default
        $productType = $request->product_type ?? 'App\Models\Hotel';

        // Ensure product type is allowed
        if (!in_array($productType, $this->allowedProductTypes)) {
            $productType = 'App\Models\Hotel'; // Default to Hotel if invalid
        }

        // Use a single query with JOIN instead of getting IDs first
        $query = Booking::query()
            ->join('booking_items', function ($join) use ($productType) {
                $join->on('bookings.id', '=', 'booking_items.booking_id')
                    ->where('booking_items.product_type', $productType);
            })
            ->select('bookings.*')
            ->distinct() // Ensure we don't get duplicate bookings
            ->whereHas('items', function($query) use ($request, $productType) {
                $query->where('product_type', $productType)
                    ->when($request->hotel_name, function($q) use ($request, $productType) {
                        if ($productType == 'App\Models\Hotel') {
                            $q->whereRaw("EXISTS (SELECT 1 FROM hotels WHERE booking_items.product_id = hotels.id AND hotels.name LIKE ?)", ['%' . $request->hotel_name . '%']);
                        } elseif ($productType == 'App\Models\EntranceTicket') {
                            $q->whereRaw("EXISTS (SELECT 1 FROM entrance_tickets WHERE booking_items.product_id = entrance_tickets.id AND entrance_tickets.name LIKE ?)", ['%' . $request->hotel_name . '%']);
                        } elseif ($productType == 'App\Models\PrivateVanTour') {
                            $q->whereRaw("EXISTS (SELECT 1 FROM private_van_tours WHERE booking_items.product_id = private_van_tours.id AND private_van_tours.name LIKE ?)", ['%' . $request->hotel_name . '%']);
                        }
                    })
                    // Add invoice status search in booking_items
                    ->when($request->invoice_status, function($q) use ($request) {
                        if ($request->invoice_status === 'not_receive') {
                            $q->where(function($subquery) {
                                $subquery->whereNull('booking_items.booking_status')
                                        ->orWhere('booking_items.booking_status', '')
                                        ->orWhere('booking_items.booking_status', 'not_receive');
                            });
                        } else {
                            $q->where('booking_items.booking_status', $request->invoice_status);
                        }
                    });
            })
            ->with([
                'customer:id,name,email', // Select only needed customer fields
                // Only load items of the selected product type and apply the hotel_name filter to the relationship
                'items' => function($query) use ($productType, $request) {
                    $query->where('product_type', $productType)
                          // Filter items by hotel_name if provided
                          ->when($request->hotel_name, function($q) use ($request, $productType) {
                              if ($productType == 'App\Models\Hotel') {
                                  $q->whereRaw("EXISTS (SELECT 1 FROM hotels WHERE booking_items.product_id = hotels.id AND hotels.name LIKE ?)", ['%' . $request->hotel_name . '%']);
                              } elseif ($productType == 'App\Models\EntranceTicket') {
                                  $q->whereRaw("EXISTS (SELECT 1 FROM entrance_tickets WHERE booking_items.product_id = entrance_tickets.id AND entrance_tickets.name LIKE ?)", ['%' . $request->hotel_name . '%']);
                              } elseif ($productType == 'App\Models\PrivateVanTour') {
                                  $q->whereRaw("EXISTS (SELECT 1 FROM private_van_tours WHERE booking_items.product_id = private_van_tours.id AND private_van_tours.name LIKE ?)", ['%' . $request->hotel_name . '%']);
                              }
                          })
                          // Also filter items by invoice status if provided
                          ->when($request->invoice_status, function($q) use ($request) {
                              if ($request->invoice_status === 'not_receive') {
                                  $q->where(function($subquery) {
                                      $subquery->whereNull('booking_status')
                                              ->orWhere('booking_status', '')
                                              ->orWhere('booking_status', 'not_receive');
                                  });
                              } else {
                                  $q->where('booking_status', $request->invoice_status);
                              }
                          });
                    // Don't limit fields as BookingItemResource.php needs all fields
                },
                'items.product:id,name', // Select only needed product fields
            ])
            ->when($request->booking_daterange, function ($query) use ($request, $productType) {
                $dates = explode(',', $request->booking_daterange);
                // Filter bookings that have at least one item with service_date within the given range
                $query->whereHas('items', function($q) use ($dates, $productType) {
                    $q->where('product_type', $productType)
                      ->whereBetween('service_date', $dates);
                });
            })
            ->when($request->user_id, function ($query) use ($request) {
                $query->where('created_by', $request->user_id)
                    ->orWhere('past_user_id', $request->user_id);
            });

        // Add filter for CRM ID
        $query->when($request->crm_id, function ($query) use ($request) {
            $query->where('bookings.crm_id','LIKE','%'. $request->crm_id . '%');
        });

        // Add filter for customer name
        $query->when($request->customer_name, function ($query) use ($request) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('customers.name', 'LIKE', '%' . $request->customer_name . '%');
            });
        });

        // Apply user role restrictions
        if (!(Auth::user()->role === 'super_admin' || Auth::user()->role === 'reservation' || Auth::user()->role === 'auditor')) {
            $query->where(function ($q) {
                $q->where('created_by', Auth::id())
                    ->orWhere('past_user_id', Auth::id());
            });
        }

        // Calculate totals for metadata using efficient queries - adjust to include hotel_name filter
        $totalProductAmountQuery = DB::table('booking_items')
            ->whereIn('booking_id', $query->clone()->pluck('bookings.id'))
            ->where('product_type', $productType);

        // Apply hotel_name filter to the total amount calculation
        if ($request->hotel_name) {
            if ($productType == 'App\Models\Hotel') {
                $totalProductAmountQuery->whereRaw("EXISTS (SELECT 1 FROM hotels WHERE booking_items.product_id = hotels.id AND hotels.name LIKE ?)", ['%' . $request->hotel_name . '%']);
            } elseif ($productType == 'App\Models\EntranceTicket') {
                $totalProductAmountQuery->whereRaw("EXISTS (SELECT 1 FROM entrance_tickets WHERE booking_items.product_id = entrance_tickets.id AND entrance_tickets.name LIKE ?)", ['%' . $request->hotel_name . '%']);
            } elseif ($productType == 'App\Models\PrivateVanTour') {
                $totalProductAmountQuery->whereRaw("EXISTS (SELECT 1 FROM private_van_tours WHERE booking_items.product_id = private_van_tours.id AND private_van_tours.name LIKE ?)", ['%' . $request->hotel_name . '%']);
            }
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

        $totalProductAmount = $totalProductAmountQuery->sum('amount');

        // You may need to adjust this method call based on your actual implementation
        $totalExpenseAmount = 0;
        if (class_exists('BookingItemDataService')) {
            $totalExpenseAmount = BookingItemDataService::getTotalExpenseAmount($query->clone());
        }

        // Get all required bookings at once
        $bookings = $query->orderBy('bookings.created_at', 'desc')->get();

        // Group by CRM ID more efficiently
        $results = collect();
        $bookingsByCrmId = $bookings->groupBy('crm_id');

        foreach ($bookingsByCrmId as $crmId => $crmBookings) {
            // Find the latest service date
            $processedBookings = $crmBookings->map(function($booking) {
                // Group the booking items by product_id
                $booking->groupedItems = $booking->items->groupBy('product_id');
                return $booking;
            });

            $latestServiceDate = $crmBookings->max(function ($booking) {
                return $booking->items->max('service_date');
            });

            // Determine the combined expense status for all bookings in this CRM group
            $expenseStatus = 'fully_paid'; // Default assuming all are paid
            $hasFullyPaid = false;
            $hasNotPaid = false;

            // Check all booking items for their payment status
            foreach ($crmBookings as $booking) {
                foreach ($booking->items as $item) {
                    if ($item->payment_status === 'fully_paid') {
                        $hasFullyPaid = true;
                    } elseif ($item->payment_status === 'not_paid') {
                        $hasNotPaid = true;
                    }
                }
            }

            // Determine the combined expense status
            if ($hasFullyPaid && $hasNotPaid) {
                $expenseStatus = 'partially_paid';
            } elseif ($hasNotPaid && !$hasFullyPaid) {
                $expenseStatus = 'not_paid';
            } // else remains 'fully_paid'

            // Get customer payment status from the most recent booking
            $recentBooking = $crmBookings->sortByDesc('created_at')->first();
            $customerPaymentStatus = $recentBooking->payment_status ?? 'not_paid';

            // Calculate total amount only for the filtered items
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

        // Apply post-grouping filters for expense_status
        if ($request->expense_status) {
            $status = $request->expense_status;

            $results = $results->filter(function ($group) use ($status) {
                return $group['expense_status'] === $status;
            });
        }

        // Apply post-grouping filters for customer_payment_status
        if ($request->customer_payment_status) {
            $status = $request->customer_payment_status;

            $results = $results->filter(function ($group) use ($status) {
                return $group['customer_payment_status'] === $status;
            });
        }

        // Define payment status sorting priority
        $paymentStatusPriority = [
            'not_paid' => 3,
            'partially_paid' => 2,
            'fully_paid' => 1
        ];

        // Get sorting parameters
        $orderBy = $request->order_by ?? 'service_date'; // Default sort field
        $orderDirection = $request->order_direction ?? 'desc'; // Default direction

        // Apply sorting based on the specified field and direction
        if ($orderBy === 'customer_payment_status') {
            // Sort by customer payment status using the calculated value
            $sortMethod = $orderDirection === 'desc' ? 'sortByDesc' : 'sortBy';
            $results = $results->$sortMethod(function ($group) use ($paymentStatusPriority) {
                $status = $group['customer_payment_status'] ?? 'not_paid';
                return $paymentStatusPriority[$status] ?? 0;
            });
        }
        elseif ($orderBy === 'expense_status') {
            // Sort by expense payment status using the calculated value
            $sortMethod = $orderDirection === 'desc' ? 'sortByDesc' : 'sortBy';
            $results = $results->$sortMethod(function ($group) use ($paymentStatusPriority) {
                $status = $group['expense_status'] ?? 'not_paid';
                return $paymentStatusPriority[$status] ?? 0;
            });
        }
        elseif ($orderBy === 'service_date') {
            // Sort by latest service date (default)
            $sortMethod = $orderDirection === 'desc' ? 'sortByDesc' : 'sortBy';
            $results = $results->$sortMethod('latest_service_date');
        }
        else {
            // Default sort by latest service date
            $results = $results->sortByDesc('latest_service_date');
        }

        // Always ensure we have values after sorting
        $results = $results->values();

        // Calculate pagination values
        $total = $results->count();
        $perPage = $limit;
        $offset = ($page - 1) * $perPage;

        // Slice the results for the current page
        $paginatedResults = $results->slice($offset, $perPage)->values();

        // Create a paginator
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedResults,
            $total,
            $perPage,
            $page,
            ['path' => \Illuminate\Support\Facades\Request::url()]
        );

        // Create a more appropriate title based on product type
        $titlePrefix = $this->getProductTypeTitle($productType);

        // Create the resource collection with additional data
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

        return $this->success($response->response()->getData(), $titlePrefix . ' Reservations Grouped By CRM ID');
    }

    /**
     * Get detailed reservation by booking ID
     *
     * @param Request $request
     * @param int $id - The booking ID
     * @param int|null $product_id - Optional product ID filter
     * @return JsonResponse
     */
    public function getHotelReservationDetail(Request $request, $id, $product_id = null)
    {
        // Get product type from request or use default
        $productType = $request->product_type ?? 'App\Models\Hotel';

        // Ensure product type is allowed
        if (!in_array($productType, $this->allowedProductTypes)) {
            $productType = 'App\Models\Hotel'; // Default to Hotel if invalid
        }

        // Query for the specific booking by ID
        $booking = Booking::query()
            ->with([
                'customer',
                // Only load specified product type items, filter by product_id if provided
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

        // If no booking found, return error
        if (!$booking) {
            return $this->error('No reservation found for the provided booking ID', 404);
        }

        // Apply user role restrictions
        if (!(Auth::user()->role === 'super_admin' || Auth::user()->role === 'reservation' || Auth::user()->role === 'auditor')) {
            if ($booking->created_by !== Auth::id() && $booking->past_user_id !== Auth::id()) {
                return $this->error('You do not have permission to view this booking', 403);
            }
        }

        // Get the total items count (without filters)
        $totalItemsCount = DB::table('booking_items')
            ->where('booking_id', $id)
            ->count();

        // Find other bookings with the same CRM ID to group them
        $relatedBookings = null;
        if ($booking->crm_id) {
            $relatedBookings = Booking::query()
                ->with([
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
                ->where('id', '!=', $booking->id) // Exclude the current booking
                ->get();
        }

        // Prepare the result with grouped information
        $result = [
            'booking' => new ReservationGroupByResource($booking),
            'total_items_count' => $totalItemsCount, // Add total items count here
            'group_info' => null
        ];

        // If there are related bookings with the same CRM ID, include group information
        if ($relatedBookings && $relatedBookings->count() > 0) {
            // Combine current booking with related bookings for calculations
            $allBookings = collect([$booking])->merge($relatedBookings);

            $result['group_info'] = [
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

        // Create a more appropriate title based on product type
        $titlePrefix = $this->getProductTypeTitle($productType);

        return $this->success($result, $titlePrefix . ' Reservation Detail');
    }

    /**
     * Get private van tour reservation details by booking ID
     *
     * @param Request $request
     * @param int $id - The booking ID
     * @return JsonResponse
     */
    /**
     * Get private van tour reservation details by booking ID
     *
     * @param Request $request
     * @param int $id - The booking ID
     * @return JsonResponse
     */
    /**
     * Get private van tour reservation details by booking ID with car booking information
     *
     * @param Request $request
     * @param int $id - The booking ID
     * @return JsonResponse
     */
    public function getPrivateVanTourReservationDetail(Request $request, $id)
    {
        // Set product type to PrivateVanTour
        $productType = 'App\Models\PrivateVanTour';

        // Query for the specific booking by ID
        $booking = Booking::query()
            ->with([
                'customer',
                // Load all PrivateVanTour items for this booking with necessary relationships
                'items' => function($query) use ($productType) {
                    $query->where('product_type', $productType);
                },
                'items.product',
                'items.variation',
                // Add car booking related relationships - ensure these match your actual model relationships
                'items.reservationCarInfo',
                'items.reservationCarInfo.supplier',
                'items.reservationCarInfo.driverInfo',
                'items.reservationCarInfo.driverInfo.driver',
                'items.reservationCarInfo.driverInfo.driver.contact',
                'items.reservationInfo:id,booking_item_id,pickup_location,pickup_time',
            ])
            ->where('id', $id)
            ->first();

        // If no booking found, return error
        if (!$booking) {
            return $this->error('No reservation found for the provided booking ID', 404);
        }

        // Apply user role restrictions
        if (!(Auth::user()->role === 'super_admin' || Auth::user()->role === 'reservation' || Auth::user()->role === 'auditor')) {
            if ($booking->created_by !== Auth::id() && $booking->past_user_id !== Auth::id()) {
                return $this->error('You do not have permission to view this booking', 403);
            }
        }

        // Get the total items count of PrivateVanTour type
        $totalItemsCount = DB::table('booking_items')
            ->where('booking_id', $id)
            ->where('product_type', $productType)
            ->count();

        // Find other bookings with the same CRM ID to group them
        $relatedBookings = null;
        if ($booking->crm_id) {
            $relatedBookings = Booking::query()
                ->with([
                    'customer',
                    'items' => function($query) use ($productType) {
                        $query->where('product_type', $productType);
                    },
                    'items.product',
                    'items.variation',
                    // Add same car booking relationships to related bookings
                    'items.reservationCarInfo',
                    'items.reservationCarInfo.supplier',
                    'items.reservationCarInfo.driverInfo',
                    'items.reservationCarInfo.driverInfo.driver',
                    'items.reservationCarInfo.driverInfo.driver.contact',
                    'items.reservationInfo:id,booking_item_id,pickup_location,pickup_time',
                ])
                ->where('crm_id', $booking->crm_id)
                ->where('id', '!=', $booking->id) // Exclude the current booking
                ->get();
        }

        // Enhance booking items with car booking details
        foreach ($booking->items as $item) {
            $carInfo = null;
            $driverInfo = null;
            $pickupInfo = null;
            $isAssigned = false;
            $isComplete = false;

            // Check if car is assigned and get car info
            if ($item->reservationCarInfo) {
                $isAssigned = true;
                $carInfo = [
                    'id' => $item->reservationCarInfo->id,
                    'supplier_id' => $item->reservationCarInfo->supplier_id ?? null,
                    'supplier_name' => $item->reservationCarInfo->supplier->name ?? 'N/A',
                ];

                // Check if driver info exists
                if ($item->reservationCarInfo->driverInfo &&
                    $item->reservationCarInfo->driverInfo->driver &&
                    $item->reservationCarInfo->driverInfo->driver->contact) {
                    $driverInfo = [
                        'id' => $item->reservationCarInfo->driverInfo->driver->id ?? null,
                        'name' => $item->reservationCarInfo->driverInfo->driver->name ?? 'N/A',
                        'contact' => $item->reservationCarInfo->driverInfo->driver->contact->phone ?? 'N/A',
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

            // Check if booking is complete based on logic in your completePercentage method
            $isComplete = !(
                is_null($item->reservationCarInfo) ||
                is_null($item->reservationCarInfo->supplier ?? null) ||
                is_null($item->reservationCarInfo->driverInfo ?? null) ||
                is_null($item->reservationCarInfo->driverInfo->driver ?? null) ||
                is_null($item->reservationCarInfo->driverInfo->driver->contact ?? null) ||
                is_null($item->cost_price) ||
                is_null($item->total_cost_price) ||
                is_null($item->pickup_time) ||
                is_null($item->route_plan) ||
                is_null($item->special_request) ||
                ($item->is_driver_collect && is_null($item->extra_collect_amount))
            );

            // Add all the car booking details to the item
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

        // Do the same for related bookings if they exist
        if ($relatedBookings) {
            foreach ($relatedBookings as $relatedBooking) {
                foreach ($relatedBooking->items as $item) {
                    $carInfo = null;
                    $driverInfo = null;
                    $pickupInfo = null;
                    $isAssigned = false;
                    $isComplete = false;

                    // Check if car is assigned and get car info
                    if ($item->reservationCarInfo) {
                        $isAssigned = true;
                        $carInfo = [
                            'id' => $item->reservationCarInfo->id,
                            'supplier_id' => $item->reservationCarInfo->supplier_id ?? null,
                            'supplier_name' => $item->reservationCarInfo->supplier->name ?? 'N/A',
                        ];

                        // Check if driver info exists
                        if ($item->reservationCarInfo->driverInfo &&
                            $item->reservationCarInfo->driverInfo->driver &&
                            $item->reservationCarInfo->driverInfo->driver->contact) {
                            $driverInfo = [
                                'id' => $item->reservationCarInfo->driverInfo->driver->id ?? null,
                                'name' => $item->reservationCarInfo->driverInfo->driver->name ?? 'N/A',
                                'contact' => $item->reservationCarInfo->driverInfo->driver->contact->phone ?? 'N/A',
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
                        is_null($item->reservationCarInfo->driverInfo->driver->contact ?? null) ||
                        is_null($item->cost_price) ||
                        is_null($item->total_cost_price) ||
                        is_null($item->pickup_time) ||
                        is_null($item->route_plan) ||
                        is_null($item->special_request) ||
                        ($item->is_driver_collect && is_null($item->extra_collect_amount))
                    );

                    // Add all the car booking details to the item
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
            }
        }

        // Prepare the result with grouped information
        $result = [
            'booking' => new ReservationGroupByResource($booking),
            'total_items_count' => $totalItemsCount,
            'group_info' => null
        ];

        // If there are related bookings with the same CRM ID, include group information
        if ($relatedBookings && $relatedBookings->count() > 0) {
            // Combine current booking with related bookings for calculations
            $allBookings = collect([$booking])->merge($relatedBookings);

            $result['group_info'] = [
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

        return $this->success($result, 'Private Van Tour Reservation Detail');
    }

    public function copyBookingItemsGroup(Request $request, string $bookingId, string $product_id = null)
    {
        // Get product type from request or use default
        $productType = $request->product_type ?? 'App\Models\Hotel';

        // Ensure product type is allowed
        if (!in_array($productType, $this->allowedProductTypes)) {
            $productType = 'App\Models\Hotel'; // Default to Hotel if invalid
        }

        // Find the booking
        $booking = Booking::find($bookingId);

        if (!$booking) {
            return $this->error(null, 'Booking not found', 404);
        }

        // Load booking items with related entities, filtered by product_id if provided
        $booking->load([
            'items' => function ($query) use ($product_id, $productType) {
                $query->where('product_type', $productType);
                if ($product_id) {
                    $query->where('product_id', $product_id);
                }
            },
            'items.product',
            'items.room',
            'items.variation'
        ]);

        // Check if there are any items of the specified product type
        if ($booking->items->isEmpty()) {
            $typeName = $this->getProductTypeTitle($productType, true);
            return $this->error(null, "No {$typeName} items found in this booking", 404);
        }

        // Transform each booking item using existing resource
        $detailedItems = [];
        foreach ($booking->items as $item) {
            $detailedItems[] = new BookingItemDetailResource($item);
        }

        // Also get any related bookings with the same CRM ID
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

        // Prepare response data
        $responseData = [
            'booking_id' => $booking->id,
            'crm_id' => $booking->crm_id,
            'customer_name' => $booking->customer->name ?? '-',
            'booking_date' => $booking->booking_date,
            'payment_status' => $booking->payment_status,
            'balance_due' => $booking->balance_due,
            'selling_price' => $booking->sub_total,
            'items' => $detailedItems,
            'related_items' => $relatedItems,
            'product_type' => $productType,
        ];

        // Add summary with appropriate calculation based on product type
        if ($productType == 'App\Models\Hotel') {
            $responseData['summary'] = [
                'total_rooms' => $booking->items->sum('quantity'),
                'total_nights' => $booking->items->sum(function ($item) {
                    if ($item->checkin_date && $item->checkout_date) {
                        return (int) Carbon::parse($item->checkin_date)->diff(Carbon::parse($item->checkout_date))->format("%a") * $item->quantity;
                    }
                    return 0;
                }),
                'total_amount' => $booking->items->sum('amount'),
                'total_cost' => $booking->items->sum('total_cost_price')
            ];
        } elseif ($productType == 'App\Models\EntranceTicket') {
            $responseData['summary'] = [
                'total_tickets' => $booking->items->sum('quantity'),
                'total_amount' => $booking->items->sum('amount'),
                'total_cost' => $booking->items->sum('total_cost_price')
            ];
        } elseif ($productType == 'App\Models\PrivateVanTour') {
            $responseData['summary'] = [
                'total_tours' => $booking->items->sum('quantity'),
                'total_amount' => $booking->items->sum('amount'),
                'total_cost' => $booking->items->sum('total_cost_price')
            ];
        }

        $typeName = $this->getProductTypeTitle($productType);
        return $this->success($responseData, $typeName . ' Booking Items Group Details');
    }

    /**
     * Helper method to get user-friendly product type title
     *
     * @param string $productType
     * @param bool $lowercase Whether to return title in lowercase
     * @return string
     */
    private function getProductTypeTitle($productType, $lowercase = false)
    {
        if ($productType == 'App\Models\EntranceTicket') {
            $title = 'Entrance Ticket';
        } elseif ($productType == 'App\Models\PrivateVanTour') {
            $title = 'Private Van Tour';
        } else {
            $title = 'Hotel';
        }

        return $lowercase ? strtolower($title) : $title;
    }
}
