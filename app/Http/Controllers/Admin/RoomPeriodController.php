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

        $roomService = new RoomService($room);
        $pricing = $roomService->getDailyPricing($request->checkin_date, $request->checkout_date);

        $data = [
            'room' => $room,
            'daily_pricing' => $pricing['daily'],
            'total_sale_price' => $pricing['total_sale'],
            'total_cost_price' => $pricing['total_cost'],
            'total_discount_price' => $pricing['total_discount'],
            'total_selling_price' => $pricing['total_selling_price'],
            'overall_discount_percent' => $pricing['overall_discount_percent'],
            'service_date' => $request->service_date,
        ];

        return success($data);
    }
}
