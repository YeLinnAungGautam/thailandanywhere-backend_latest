<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use HttpResponses;

    /**
     * အော်ဒါစာရင်းပြခြင်း
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $status = $request->query('status');

        $query = Order::with(['items', 'customer', 'admin', 'payments','user'])
            ->where('user_id', Auth::id());

        if ($status) {
            $query->where('order_status', $status);
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($limit);
        return $this->success(OrderResource::collection($data)
        ->additional([
            'meta' => [
                'total_page' => (int)ceil($data->total() / $data->perPage()),
            ],
        ])
        ->response()
        ->getData(), 'Booking List');
    }

    /**
     * ပစ္စည်းခြင်းတောင်းမှ အော်ဒါသို့ ပြောင်းလဲခြင်း
     */
    public function checkout(Request $request)
    {
        try {
            // အော်ဒါဒေတာပြင်ဆင်ခြင်း
            $order = [
                'user_id' => Auth::id(),
                'customer_id' => $request->customer_id,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'sold_from' => $request->sold_from,
                'order_datetime' => Carbon::now(),
                'expire_datetime' => Carbon::now()->addHours(24),
                'order_number' => 'ORD-'.Carbon::now()->format('Ymd-His').'-'.Auth::id(),
                'balance_due_date' => $request->balance_due_date,
                'order_status' => 'pending',
                'discount' => $request->discount ?? 0,
                'sub_total' => $request->total_amount,
                'grand_total' => $request->grand_total,
                'deposit_amount' => $request->deposit_amount ?? 0,
                'comment' => $request->comment ?? null,
            ];

            // အော်ဒါဖန်တီးခြင်း
            $order = Order::create($order);

            // ပစ္စည်းများထည့်သွင်းခြင်း
            if ($request->has('items') && is_array($request->items)) {
                $orderItems = $this->assignOrderItems($order, $request->items);
            }

            // ပြန်လည်ဆွဲယူခြင်း (refresh) ဖြင့် အချက်အလက်အားလုံးပါဝင်မှုကိုသေချာစေခြင်း
            $order = Order::with(['items', 'customer'])->find($order->id);

            // Response ပြန်ပို့ခြင်း
            return $this->success(new OrderResource($order), 'အော်ဒါအောင်မြင်စွာ ဖန်တီးပြီးပါပြီ။');
        } catch (\Exception $e) {

            return $this->error(null, $e->getMessage());
        }
    }

    /**
     * အော်ဒါပစ္စည်းများထည့်သွင်းခြင်း
     */
    private function assignOrderItems($order, $items)
    {
        $createdItems = [];

        foreach ($items as $item) {
            $orderItem = new OrderItem([
                'order_id' => $order->id,
                'product_type' => $item['product_type'] ?? 'App\Models\Product',
                'product_id' => $item['product_id'],
                'variation_id' => $item['variation_id'] ?? null,
                'car_id' => $item['car_id'] ?? null,
                'room_id' => $item['room_id'] ?? null,
                'service_date' => $item['service_date'] ?? null,
                'checkin_date' => $item['checkin_date'] ?? null,
                'checkout_date' => $item['checkout_date'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'selling_price' => $item['selling_price'],
                'total_selling_price' => $item['total_selling_price'],
                'cost_price' => $item['cost_price'] ?? null,
                'total_cost_price' => $item['total_cost_price'] ?? null,
                'discount' => $item['discount'] ?? 0,
                'special_request' => $item['special_request'] ?? null,
                'route_plan' => $item['route_plan'] ?? null,
                'pickup_location' => $item['pickup_location'] ?? null,
                'pickup_time' => $item['pickup_time'] ?? null,
            ]);

            $order->items()->save($orderItem);
            $createdItems[] = $orderItem;
        }

        // Cart ကို ရှင်းလင်းရန် (လိုအပ်ပါက)

        try {
            Cart::where('user_id', Auth::id())->delete();
        } catch (\Exception $e) {
            return $this->error(null, $e->getMessage());
        }

        return $createdItems;
    }


    /**
     * အော်ဒါအသေးစိတ်ကြည့်ရှုခြင်း
     */
    public function show($id)
    {
        $order = Order::with(['items', 'customer', 'admin', 'payments', 'booking','user'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return $this->success([
            'order' => $order,
            'remaining_time' => $order->isExpired() ? 0 : Carbon::now()->diffInSeconds($order->expire_datetime),
        ], 'အော်ဒါအသေးစိတ်');
    }

    /**
     * အော်ဒါကို ပယ်ဖျက်ခြင်း
     */
    public function cancelOrder(Request $request, $id)
    {
        $order = Order::where('user_id', Auth::id())->findOrFail($id);

        if ($order->order_status !== 'pending' && $order->order_status !== 'processing') {
            return $this->error('ဤအော်ဒါကို ယခုအချိန်တွင် ပယ်ဖျက်ခွင့်မရှိပါ', 400);
        }

        if ($order->booking_id) {
            return $this->error('ဘွတ်ကင်အဖြစ်ပြောင်းလဲပြီးသော အော်ဒါကို ပယ်ဖျက်၍မရပါ', 400);
        }

        $order->update([
            'order_status' => 'cancelled',
            'comment' => $request->comment ?? $order->comment,
        ]);

        // return $this->success([
        //     'order' => $order,
        // ], 'အော်ဒါကို အောင်မြင်စွာ ပယ်ဖျက်ပြီးပါပြီ။');
        return $this->success(new OrderResource($order), 'အော်ဒါကို အောင်မြင်စွာ ပယ်ဖျက်ပြီးပါပြီ။');
    }
}
