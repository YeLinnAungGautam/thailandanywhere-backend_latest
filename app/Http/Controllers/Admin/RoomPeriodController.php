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
            'checkin_date' => 'required|date',
            'checkout_date' => 'required|date',
        ]);

        $period = $request->checkin_date . ' , ' . $request->checkout_date;

        $data = [
            'room' => $room,
            'service_date' => $request->service_date,
            'room_price' => (new RoomService($room))->getRoomPrice($period),
        ];

        return success($data);
    }
}
