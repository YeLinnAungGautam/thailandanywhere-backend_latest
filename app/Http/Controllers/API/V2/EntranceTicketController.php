<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\Request;

class EntranceTicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Room::query()
            ->with('hotel', 'periods', 'images')
            ->when($request->hotel_id, fn ($query) => $query->where('hotel_id', $request->hotel_id))
            ->when($request->period_name, function ($query) use ($request) {
                $query->whereHas('periods', fn ($q) => $q->where('period_name', 'LIKE', "%{$request->period_name}%"));
            });

        if($request->order_by_price) {
            if($request->order_by_price == 'low_to_high') {
                $query->orderBy('room_price');
            } elseif($request->order_by_price == 'high_to_low') {
                $query->orderByDesc('room_price');
            }
        }

        $items = $query->paginate($request->limit ?? 10);

        return RoomResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $room_id)
    {
        if(is_null($room = Room::find($room_id))) {
            return notFoundMessage();
        }

        $room->load('hotel', 'periods', 'images');

        return success(new RoomResource($room));
    }
}
