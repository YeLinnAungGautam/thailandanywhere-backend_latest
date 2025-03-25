<?php
namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $items = Booking::query()
            ->where('user_id', $request->user()->id)
            ->when($request->search, fn ($s_query) => $s_query->where('name', 'LIKE', "%{$request->search}%"))
            ->paginate($request->limit ?? 10);

        return BookingResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
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
