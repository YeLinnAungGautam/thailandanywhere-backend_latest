<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $items = Room::with('hotel', 'periods', 'images')->paginate($request->limit ?? 10);

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
