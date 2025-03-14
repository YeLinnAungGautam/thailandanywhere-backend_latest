<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        // Use a single query with JOIN instead of getting IDs first
        $query = Booking::query()
            ->join('booking_items', function ($join) {
                $join->on('bookings.id', '=', 'booking_items.booking_id')
                     ->where('booking_items.product_type', 'App\Models\Hotel');
            })
            ->select('bookings.*')
            ->distinct() // Ensure we don't get duplicate bookings
            ->whereHas('items', function($query) use ($request) {
                $query->where('product_type', 'App\Models\Hotel')
                    ->when($request->hotel_name, function($q) use ($request) {
                        $q->whereRaw("EXISTS (SELECT 1 FROM hotels WHERE booking_items.product_id = hotels.id AND hotels.name LIKE ?)", ['%' . $request->hotel_name . '%']);
                    });
            })
            ->with([
                'customer:id,name,email', // Select only needed customer fields
                // Only load hotel items and select all necessary fields
                // Make sure to include all fields needed by BookingItemResource
                'items' => function($query) {
                    $query->where('product_type', 'App\Models\Hotel');
                    // Don't limit fields as BookingItemResource.php needs all fields
                },
                'items.product:id,name', // Select only needed product fields
            ])
            ->when($request->booking_daterange, function ($query) use ($request) {
                $dates = explode(',', $request->booking_daterange);
                $query->whereBetween('booking_date', $dates);
            })
            ->when($request->user_id, function ($query) use ($request) {
                $query->where('created_by', $request->user_id)
                      ->orWhere('past_user_id', $request->user_id);
            });

        // Apply user role restrictions
        if (!(Auth::user()->role === 'super_admin' || Auth::user()->role === 'reservation' || Auth::user()->role === 'auditor')) {
            $query->where(function ($q) {
                $q->where('created_by', Auth::id())
                    ->orWhere('past_user_id', Auth::id());
            });
        }

        // Add indexes for these columns if they don't exist
        // Create a migration: php artisan make:migration add_indexes_to_bookings_table
        // $table->index('crm_id');
        // $table->index('created_by');
        // $table->index('past_user_id');
        // $table->index('booking_date');

        // Calculate totals for metadata using efficient queries
        $totalHotelAmount = DB::table('booking_items')
            ->whereIn('booking_id', $query->clone()->pluck('bookings.id'))
            ->where('product_type', 'App\Models\Hotel')
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
            $latestServiceDate = $crmBookings->max(function ($booking) {
                return $booking->items->max('service_date');
            });

            $results->push([
                'crm_id' => $crmId,
                'latest_service_date' => $latestServiceDate,
                'total_bookings' => $crmBookings->count(),
                'total_amount' => $crmBookings->sum(function ($booking) {
                    return $booking->items->sum('amount');
                }),
                'bookings' => BookingResource::collection($crmBookings),
            ]);
        }

        // Sort by latest service date
        $results = $results->sortByDesc('latest_service_date')->values();

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

        // Create the resource collection with additional data
        $response = (new \Illuminate\Http\Resources\Json\AnonymousResourceCollection(
            $paginator,
            \App\Http\Resources\HotelGroupResource::class
        ))->additional([
            'meta' => [
                'total_page' => (int)ceil($total / $perPage),
                'total_amount' => $totalHotelAmount,
                'total_expense_amount' => $totalExpenseAmount,
            ],
        ]);

        // Add cache if appropriate (e.g., for 5 minutes)
        // return Cache::remember('hotel_reservations_' . md5($request->fullUrl()), 300, function() use ($response) {
        //     return $this->success($response->response()->getData(), 'Hotel Reservations Grouped By CRM ID');
        // });

        // Return the success response
        return $this->success($response->response()->getData(), 'Hotel Reservations Grouped By CRM ID');
    }

    /**
     * Get detailed hotel reservation by booking ID
     *
     * @param Request $request
     * @param int $id - The booking ID
     * @return JsonResponse
     */
    public function getHotelReservationDetail(Request $request, $id)
    {
        // Query for the specific booking by ID
        $booking = Booking::query()
            ->with([
                'customer',
                // Only load hotel items
                'items' => function($query) {
                    $query->where('product_type', 'App\Models\Hotel');
                },
                'items.product',
            ])
            ->where('id', $id)
            ->first();

        // If no booking found, return error
        if (!$booking) {
            return $this->error('No hotel reservation found for the provided booking ID', 404);
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
                    'items' => function($query) {
                        $query->where('product_type', 'App\Models\Hotel');
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

        return $this->success($result, 'Hotel Reservation Detail');
    }

    public function copyBookingItemsGroup(string $bookingId)
    {
        // Find the booking
        $booking = Booking::find($bookingId);

        if (!$booking) {
            return $this->error(null, 'Booking not found', 404);
        }

        // Load booking items with related entities
        $booking->load([
            'items' => function ($query) {
                $query->where('product_type', 'App\Models\Hotel');
            },
            'items.product',
            'items.room',
            'items.variation'
        ]);

        // Check if there are any hotel items
        if ($booking->items->isEmpty()) {
            return $this->error(null, 'No hotel items found in this booking', 404);
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
                'items' => function ($query) {
                    $query->where('product_type', 'App\Models\Hotel');
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
            'summary' => [
                'total_rooms' => $booking->items->sum('quantity'),
                'total_nights' => $booking->items->sum(function ($item) {
                    if ($item->product_type == 'App\Models\Hotel' && $item->checkin_date && $item->checkout_date) {
                        return (int) Carbon::parse($item->checkin_date)->diff(Carbon::parse($item->checkout_date))->format("%a") * $item->quantity;
                    }
                    return 0;
                }),
                'total_amount' => $booking->items->sum('amount'),
                'total_cost' => $booking->items->sum('total_cost_price')
            ]
        ];

        return $this->success($responseData, 'Booking Items Group Details');
    }
}
