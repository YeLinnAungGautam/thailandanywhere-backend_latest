<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\PartnerRoomRateService;
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

        $room_rates = [];
        $partner = $room->hotel->partners->first();
        $is_incomplete_allotment = false;

        if ($partner) {
            $roomRateService = new PartnerRoomRateService($partner->id, $room->id);
            $room_rates = $roomRateService->getRateForDaterange($request->checkin_date, $request->checkout_date);
            $is_incomplete_allotment = $roomRateService->isIncompleteAllotment($request->checkin_date, $request->checkout_date);
        }

        // ✅ Partner discount ကို daily_pricing တွင် merge လုပ်
        $daily_pricing = array_map(function ($day) use ($room_rates) {
            $date = $day['date'];
            $partner_discount = 0;

            if (isset($room_rates[$date])) {
                $partner_discount = (float) $room_rates[$date]['discount'];
            }

            $day['partner_discount']  = $partner_discount;
            $day['sale_price']     = $day['selling_price'] - $partner_discount;
            $day['cost_price']        = $day['cost_price'] - $partner_discount;

            return $day;
        }, $pricing['daily']);

        // ✅ Total များကို recalculate လုပ်
        $total_selling_price = array_sum(array_column($daily_pricing, 'sale_price'));
        $total_cost_price    = array_sum(array_column($daily_pricing, 'cost_price'));

        $data = [
            'room'                    => $room,
            'daily_pricing'           => $daily_pricing,
            // 'total_sale_price'        => $pricing['total_sale'],
            'total_cost_price'        => $total_cost_price,       // ✅ recalculated
            'total_discount_price'    => $pricing['total_discount'],
            'total_sale_price'     => $total_selling_price,    // ✅ recalculated
            'total_selling_price'     => $total_selling_price,    // ✅ recalculated
            'overall_discount_percent' => $pricing['overall_discount_percent'],
            'service_date'            => $request->service_date,
            'room_rates'              => $room_rates,
            'is_incomplete_allotment' => $is_incomplete_allotment,
        ];

        return success($data);
    }
}
