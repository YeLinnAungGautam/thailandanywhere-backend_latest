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

    public function store(Request $request, $id){
        $booking = Booking::find($id);
        if (!$booking) {
            return failedMessage('Booking not found');
        }

        $request->validate([
            'user_id' => 'required',
        ]);

        $booking->user_id = $request->user_id;
        $booking->save();

        return success(new BookingResource($booking), 'Booking Add User successfully');
    }
}
