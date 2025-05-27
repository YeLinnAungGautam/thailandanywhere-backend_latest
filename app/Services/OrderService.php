<?php
namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;

class OrderService
{
    public function cleanupExpiredOrders(){
        try {
            $expiredOrders = Order::whereNull('booking_id')
            ->where('order_status', 'pending')
            ->where('expire_datetime', '<', Carbon::now())
            ->get();

            $count = 0;

            foreach ($expiredOrders as $order) {
                $order->order_status = 'cancelled';
                $order->comment = ($order->comment ? $order->comment . "\n" : "") .
                                  "[System] သက်တမ်းကုန်ဆုံးသွားပါသဖြင့် အလိုအလျောက်ပယ်ဖျက်ခြင်း";
                $order->save();
                $count++;
            }

            return response()->json([
                'success' => true,
                'message' => "{$count} ခု အော်ဒါများကို အလိုအလျောက်ပယ်ဖျက်ပြီးပါပြီ။",
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
