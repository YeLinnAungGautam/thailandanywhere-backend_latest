<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\Request;

class RoomPeriodController extends Controller
{
    public function index(Room $room, Request $request)
    {
        $request->validate([
            'service_date' => 'required|date',
        ]);

        $data = [
            'room' => $room,
            'service_date' => $request->service_date,
            'room_price' => (new RoomService($room))->getRoomPriceBy($request->service_date),
        ];

        return success($data);
    }
}
