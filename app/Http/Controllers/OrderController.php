<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Customer;
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
        $app_show = $request->query('app_show_status');
        $userType = $request->query('type', 'user');

        $query = Order::with(['items', 'customer', 'admin', 'payments','user'])
        ->when($userType === 'user', function($q) {
            $q->where('user_id', Auth::id());
        })
        ->when($userType === 'admin', function($q) {
            $q->where('admin_id', Auth::id());
        });

        if ($app_show == 'upcoming') {
            // $query->whereIn('app_show_status', [$app_show,null]);
            $query->where(function($q) use ($app_show) {
                $q->where('order_status', 'pending')
                  ->orWhere('order_status', 'confirmed')
                  ->orWhere('order_status', 'processing');
            });
        }else if ($app_show != 'upcoming' && $app_show != '') {
            $query->where('order_status', $app_show);
        }

        $data = $query->orderBy('balance_due_date', 'desc')->paginate($limit);
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
            $userType = $request->input('type', 'user'); // Default to 'user'
            // အော်ဒါဒေတာပြင်ဆင်ခြင်း

            $customer = Customer::where('name', $request->customer_name)->first();

            if (!$customer) {
                $customer = Customer::create([
                    'name' => $request->customer_name,
                    'phone_number' => $request->phone_number,
                ]);

                $request->merge(['customer_id' => $customer->id]);
            }else{
                $request->merge(['customer_id' => $customer->id]);
            }

            $orderData = [
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'sold_from' => $request->sold_from,
                'order_datetime' => Carbon::now(),
                'expire_datetime' => Carbon::now()->addHours(24),
                'order_number' => 'ORD-'.Carbon::now()->format('Ymd-His').'-'.Auth::id(),
                'balance_due_date' => $request->balance_due_date,
                'customer_id' => $request->customer_id,
                'discount' => $request->discount ?? 0,
                'sub_total' => $request->total_amount,
                'grand_total' => $request->grand_total,
                'deposit_amount' => $request->deposit_amount ?? 0,
                'comment' => $request->comment ?? null,
            ];

            // Set user_id or admin_id based on type
            if ($userType === 'user') {
                $orderData['user_id'] = Auth::id();
                $orderData['order_status'] = 'pending';
            } else {
                $orderData['admin_id'] = Auth::id();
                $orderData['order_status'] = 'confirmed';
            }

            // အော်ဒါဖန်တီးခြင်း
            $order = Order::create($orderData);

            // ပစ္စည်းများထည့်သွင်းခြင်း
            if ($request->has('items') && is_array($request->items)) {
                $this->assignOrderItems($order, $request->items, $userType);
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
     * အော်ဒါအချက်အလက်များကို ပြင်ဆင်ခြင်း
     */
    public function update(Request $request, $id)
    {
        try {
            $userType = Auth::user()->role ? 'admin' : 'user'; // Default to 'admin'

            // Find the order with authorization check
            $order = Order::when($userType === 'user', function($q) {
                    $q->where('user_id', Auth::user()->id);
                })
                ->when($userType === 'admin', function($q) {
                    $q->where('admin_id', Auth::user()->id);
                })
                ->with(['items']) // Eager load items for calculation
                ->findOrFail($id);

            // Validate request has item_id and discount
            $request->validate([
                'item_id' => 'required|exists:order_items,id,order_id,'.$order->id,
                'discount' => 'required|numeric|min:0'
            ]);

            // Update the specific item's discount
            $orderItem = $order->items()->findOrFail($request->item_id);
            $orderItem->update(['discount' => $request->discount]);

            // Recalculate total discount from all items
            $totalDiscount = $order->items()->sum('discount');

            // Update order with new totals
            $order->update([
                'discount' => $totalDiscount,
                'grand_total' => $order->sub_total - $totalDiscount,
                // Keep existing comment if not provided
                'comment' => $request->comment ?? $order->comment
            ]);

            // Refresh with all relationships
            $order = Order::with(['items', 'customer'])->find($order->id);

            return $this->success(new OrderResource($order), 'Item discount updated successfully and order totals recalculated.');
        } catch (\Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    /**
     * အော်ဒါပစ္စည်းများထည့်သွင်းခြင်း
     */
    private function assignOrderItems($order, $items, $userType)
    {
        $createdItems = [];

        foreach ($items as $item) {
            $individualPricing = null;
            if (isset($item['individual_pricing'])) {
                // Keep as an array for the JSON column in order_items
                $individualPricing = $item['individual_pricing'];
            }

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
                'individual_pricing' => $individualPricing,
            ]);

            $order->items()->save($orderItem);
            $createdItems[] = $orderItem;

            //cart clean
            if (isset($item['cart_id'])) {
                $cartIdsToDelete[] = $item['cart_id'];
            }
        }

        // Cart ကို ရှင်းလင်းရန် (လိုအပ်ပါက)

        if (!empty($cartIdsToDelete)) {
            try {
                Cart::where('owner_id', Auth::id())
                    ->where('owner_type', get_class(Auth::user()))
                    ->whereIn('id', $cartIdsToDelete)
                    ->delete();
            } catch (\Exception $e) {
                // Log error but don't stop the process
                return $this->error('Failed to delete cart items: ' . $e->getMessage(), 500);
            }
        }

        return $createdItems;
    }


    public function show(Request $request, $id)
        {
            $userType = $request->query('type', 'user'); // Default to 'user'

            $order = Order::with(['items', 'customer', 'admin', 'payments', 'booking', 'user'])
                ->when($userType === 'user', function($q) {
                    $q->where('user_id', Auth::id());
                })
                ->when($userType === 'admin', function($q) {
                    $q->where('admin_id', Auth::id());
                })
                ->findOrFail($id);

            return $this->success([
                'order' => new OrderResource($order),
                'remaining_time' => $order->isExpired() ? 0 : Carbon::now()->diffInSeconds($order->expire_datetime),
            ], 'Order details');
        }

        /**
         * Cancel order with proper authorization
         */
        public function cancelOrder(Request $request, $id)
        {
            $userType = $request->query('type', 'user'); // Default to 'user'

            $order = Order::when($userType === 'user', function($q) {
                    $q->where('user_id', Auth::id());
                })
                ->when($userType === 'admin', function($q) {
                    $q->where('admin_id', Auth::id());
                })
                ->findOrFail($id);

            if ($order->order_status != 'pending') {
                return $this->error('This order cannot be cancelled at this time', 400);
            }

            if ($order->booking_id) {
                return $this->error('Cannot cancel order that has been converted to booking', 400);
            }

            $order->update([
                'order_status' => 'cancelled',
                'comment' => $request->comment ?? $order->comment,
            ]);

            return $this->success(new OrderResource($order), 'Order cancelled successfully');
        }
}
