<?php
namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        // Validate and get request parameters
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'app_show_status' => 'string',
            'type' => 'sometimes|string|in:user,admin',
            'converted_only' => 'sometimes|boolean' // New parameter to filter converted bookings
        ]);

        // Set default values
        $limit = $validated['limit'] ?? 10;
        $userType = $validated['type'] ?? 'user';
        $appShowStatus = $validated['app_show_status'] ?? 'upcoming';

        // Base query with eager loading
        $query = Booking::when(in_array($userType, ['user', 'admin']), function($q) use ($userType) {
                $column = $userType === 'user' ? 'user_id' : 'created_by';
                $q->where($column, Auth::id());
            })
            ->when($appShowStatus, fn($q) => $q->where('app_show_status', $appShowStatus))
            ->when($userType == 'admin', function($q) {
                // Only include bookings that have corresponding orders
                $q->whereHas('orders', function($subQuery) {
                    $subQuery->whereNotNull('booking_id');
                });
            });

        // Execute query with pagination
        $bookings = $query->with(['orders']) // Eager load orders relationship
            ->latest()
            ->paginate($limit);

        // Prepare response
        return $this->success(
            BookingResource::collection($bookings)
                ->additional([
                    'meta' => [
                        'total_page' => $bookings->lastPage(),
                        'current_page' => $bookings->currentPage(),
                        'per_page' => $bookings->perPage(),
                        'total_items' => $bookings->total(),
                    ],
                ]),
            'Booking list retrieved successfully'
        );
    }

    public function show(string $id)
    {
        $find = Booking::find($id);

        if (!$find) {
            return failedMessage('Data not found');
        }

        return success(new BookingResource($find), 'Booking Detail');
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
