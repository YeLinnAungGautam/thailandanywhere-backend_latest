<?php
namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    use HttpResponses;
    public function index(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'app_show_status' => 'string',
            'type' => 'sometimes|string|in:user,admin',
            'converted_only' => 'string',
            'start_date' => 'date_format:Y-m-d', // New: Date range filter (e.g., 2025-01-01)
            'end_date' => 'date_format:Y-m-d|after_or_equal:start_date', // Must be >= start_date
            'limit' => 'integer|min:1' // Added validation for limit
        ]);

        // Set defaults
        $limit = $validated['limit'] ?? 10;
        $userType = $validated['type'] ?? 'user';
        $appShowStatus = $validated['app_show_status'] ?? '';
        $convertedOnly = $validated['converted_only'] ?? 'false';
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        // Base query
        $query = Booking::when(in_array($userType, ['user', 'admin']), function($q) use ($userType) {
                $column = $userType === 'user' ? 'user_id' : 'created_by';
                $q->where($column, Auth::user()->id);
            })
            ->when($appShowStatus == 'upcoming', function($q) {
                $q->where(function($subQuery) {
                    $subQuery->where('app_show_status', 'upcoming')
                        ->orWhereNull('app_show_status');
                });
            })
            ->when($appShowStatus && $appShowStatus != 'upcoming', function($q) use ($appShowStatus) {
                $q->where('app_show_status', $appShowStatus);
            })
            ->when($convertedOnly == 'true', function($q) {
                $q->whereHas('orders', function($subQuery) {
                    $subQuery->whereNotNull('booking_id');
                });
            })
            ->when($startDate && $endDate, function($q) use ($startDate, $endDate) {
                // Date range filter: Bookings that overlap with the requested range
                $q->where(function($subQuery) use ($startDate, $endDate) {
                    $subQuery->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function($q) use ($startDate, $endDate) {
                            // Case where booking spans the entire range
                            $q->where('start_date', '<=', $startDate)
                              ->where('end_date', '>=', $endDate);
                        });
                });
            })
            ->orderBy('start_date', 'asc');

        // Get paginated results
        $bookings = $query->with(['orders', 'items'])
            ->latest()
            ->paginate($limit);

        // Count booking items by product type
        $productTypeCounts = [];
        if ($bookings->isNotEmpty()) {
            $bookingIds = $bookings->pluck('id');
            $productTypeCounts = DB::table('booking_items')
                ->whereIn('booking_id', $bookingIds)
                ->select('product_type', DB::raw('count(*) as count'))
                ->groupBy('product_type')
                ->pluck('count', 'product_type')
                ->toArray();
        }

        // Prepare response
        return $this->success(
            BookingResource::collection($bookings)->additional([
                'meta' => [
                    'total_page' => $bookings->lastPage(),
                    'product_type_counts' => $productTypeCounts,
                ]
            ])->response()
            ->getData(),
            'Bookings retrieved'
        );
    }

    public function show(string $id)
    {
        $find = Booking::find($id);

        if (!$find) {
            return failedMessage('Data not found');
        }

        return $this->success(new BookingResource($find), 'Data retrieved successfully');
    }

    public function store(Request $request, $id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return failedMessage('Booking not found');
        }

        $request->validate([
            'user_id' => 'required',
        ]);

        // Check if the booking already has a user_id assigned
        if ($booking->user_id) {
            return failedMessage('This booking is already assigned to a user');
        }

        $booking->user_id = $request->user_id;
        $booking->save();

        return success(new BookingResource($booking), 'Booking Add User successfully');
    }

    public function checkBookingHasUser($id)
    {
        // Find the booking by ID
        $booking = Booking::find($id);

        // If booking doesn't exist, return error response
        if (!$booking) {
            return failedMessage('Booking not found');
        }

        // Check if booking has a user assigned
        $hasUser = !is_null($booking->user_id);

        // Return appropriate response based on your application's format
        if ($hasUser) {
            return success([
                'booking_id' => $booking->id,
                'has_user' => true
            ], 'Booking is already assigned to a user');
        } else {
            return success([
                'booking_id' => $booking->id,
                'has_user' => false
            ], 'Booking has no user assigned');
        }
    }
}
