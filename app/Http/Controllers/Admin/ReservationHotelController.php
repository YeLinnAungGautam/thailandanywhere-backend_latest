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
        'App\Models\EntranceTicket'
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
                        }
                    });
            })
            ->with([
                'customer:id,name,email', // Select only needed customer fields
                // Only load items of the selected product type
                'items' => function($query) use ($productType) {
                    $query->where('product_type', $productType);
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

        // Calculate totals for metadata using efficient queries
        $totalProductAmount = DB::table('booking_items')
            ->whereIn('booking_id', $query->clone()->pluck('bookings.id'))
            ->where('product_type', $productType)
            ->sum('amount');

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

            $results->push([
                'crm_id' => $crmId,
                'latest_service_date' => $latestServiceDate,
                'total_bookings' => $crmBookings->count(),
                'total_amount' => $crmBookings->sum(function ($booking) {
                    return $booking->items->sum('amount');
                }),
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
        $titlePrefix = $productType == 'App\Models\EntranceTicket' ? 'Entrance Ticket' : 'Hotel';

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
        $titlePrefix = $productType == 'App\Models\EntranceTicket' ? 'Entrance Ticket' : 'Hotel';

        return $this->success($result, $titlePrefix . ' Reservation Detail');
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
            $typeName = $productType == 'App\Models\EntranceTicket' ? 'entrance ticket' : 'hotel';
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
        } else {
            $responseData['summary'] = [
                'total_tickets' => $booking->items->sum('quantity'),
                'total_amount' => $booking->items->sum('amount'),
                'total_cost' => $booking->items->sum('total_cost_price')
            ];
        }

        $typeName = $productType == 'App\Models\EntranceTicket' ? 'Entrance Ticket' : 'Hotel';
        return $this->success($responseData, $typeName . ' Booking Items Group Details');
    }
}
