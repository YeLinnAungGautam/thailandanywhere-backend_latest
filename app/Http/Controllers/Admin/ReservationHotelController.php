<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\BookingItemDataService;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
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

        // Get all booking IDs that have at least one hotel product
        $hotelBookingIds = BookingItem::query()
            ->select('booking_id')
            ->where('product_type', 'App\Models\Hotel')
            ->distinct()
            ->pluck('booking_id')
            ->toArray();

        // Now query bookings with those IDs
        $query = Booking::query()
            ->with([
                'customer',
                // Only load hotel items using a constraint on the relationship
                'items' => function($query) {
                    $query->where('product_type', 'App\Models\Hotel');
                },
                'items.product',
            ])
            ->whereIn('id', $hotelBookingIds)
            ->when($request->booking_daterange, function ($query) use ($request) {
                $dates = explode(',', $request->booking_daterange);
                $query->whereBetween('booking_date', $dates);
            })
            ->when($request->user_id, function ($query) use ($request) {
                $query->where('created_by', $request->user_id)
                    ->orWhere('past_user_id', $request->user_id);
            })
            ->orderBy('created_at', 'desc'); // Order by created_at (server date) in descending order

        // Apply user role restrictions similar to the original endpoint
        if (!(Auth::user()->role === 'super_admin' || Auth::user()->role === 'reservation' || Auth::user()->role === 'auditor')) {
            $query->where(function ($q) {
                $q->where('created_by', Auth::id())
                    ->orWhere('past_user_id', Auth::id());
            });
        }

        // Group by CRM ID
        $results = $query->get()
            ->groupBy('crm_id')
            ->map(function ($bookings, $crmId) {
                // Find the most recent service date for sorting
                $latestServiceDate = $bookings->max(function ($booking) {
                    // Only hotel items are loaded, so this works correctly
                    return $booking->items->max('service_date');
                });

                return [
                    'crm_id' => $crmId,
                    'latest_service_date' => $latestServiceDate,
                    'total_bookings' => $bookings->count(),
                    'total_amount' => $bookings->sum(function ($booking) {
                        // This sum already only includes hotel items
                        return $booking->items->sum('amount');
                    }),
                    'bookings' => BookingResource::collection($bookings),
                ];
            })
            ->sortByDesc('latest_service_date')
            ->values();

        // Create a custom paginator for our grouped results
        $perPage = $limit;
        $page = $request->input('page', 1);
        $total = $results->count();
        $offset = ($page - 1) * $perPage;

        // Slice the results for the current page
        $paginatedResults = $results->slice($offset, $perPage)->values();

        // Create a custom paginator instance
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedResults,
            $total,
            $perPage,
            $page,
            ['path' => \Illuminate\Support\Facades\Request::url()]
        );

        // Calculate totals for all hotel items
        $totalHotelAmount = $query->withCount(['items as total_hotel_amount' => function($q) {
            $q->where('product_type', 'App\Models\Hotel')
            ->select(DB::raw('SUM(amount)'));
        }])->get()->sum('total_hotel_amount');

        // You may need to adjust this method call based on your actual implementation
        $totalExpenseAmount = 0;
        if (class_exists('BookingItemDataService')) {
            $totalExpenseAmount = BookingItemDataService::getTotalExpenseAmount($query);
        }

        // Use a resource collection with additional data
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

        return $this->success($response->response()->getData(), 'Hotel Reservations Grouped By CRM ID');
    }
}
