<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderManager;
use App\Services\PartnerRoomRateService;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

        $query = Order::with(['items', 'customer', 'admin', 'payments', 'user'])
            ->when($userType === 'user', function ($q) {
                $q->where('user_id', Auth::id());
            })
            ->when($userType === 'admin', function ($q) {
                $q->where('admin_id', Auth::id());
            });

        if ($app_show == 'upcoming') {
            // $query->whereIn('app_show_status', [$app_show,null]);
            $query->where(function ($q) use ($app_show) {
                $q->where('order_status', 'pending')
                    ->orWhere('order_status', 'confirmed')
                    ->orWhere('order_status', 'processing');
            });
        } elseif ($app_show != 'upcoming' && $app_show != '') {
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

            // Check if user is authenticated
            if (!Auth::check()) {
                return $this->error(null, 'User must be authenticated to place an order.');
            }

            $customer = Customer::where('name', $request->customer_name)->first();

            if (!$customer) {
                $customer = Customer::create([
                    'name' => $request->customer_name,
                    'phone_number' => $request->phone_number,
                ]);
            }

            // Verify customer was created/found
            if (!$customer || !$customer->id) {
                return $this->error(null, 'Failed to create or find customer.', 500);
            }

            $request->merge(['customer_id' => $customer->id]);

            $payload = $request->all();
            if ($request->sold_from == 'mobile') {
                $payload = OrderManager::formatMobileOrderData($request->all());
            }

            $orderData = [
                'phone_number' => $payload['phone_number'] ?? null,
                'email' => $payload['email'] ?? null,
                'sold_from' => $payload['sold_from'] ?? null,
                'order_datetime' => Carbon::now(),
                'expire_datetime' => Carbon::now()->addHours(24),
                'order_number' => 'ORD-'.Carbon::now()->format('Ymd-His').'-'.Auth::id(),
                'balance_due_date' => $payload['balance_due_date'] ?? null,
                'customer_id' => $customer->id ?? null,
                'discount' => $payload['discount'] ?? 0,
                'sub_total' => $payload['total_amount'] ?? 0,
                'grand_total' => $payload['grand_total'] ?? 0,
                'deposit_amount' => $payload['deposit_amount'] ?? 0,
                'comment' => $payload['comment'] ?? null,
            ];

            // $authenticatedUserId = Auth::id();

            // return $this->success(Auth::id(), 'Order created successfully');

            // Set user_id or admin_id based on type
            if ($userType === 'user') {
                $orderData['user_id'] = Auth::id();
                $orderData['admin_id'] = $payload['admin_id'] ?? null;
                $orderData['order_status'] = 'pending';
                $orderData['is_customer_create'] = '1';
            } else {
                $orderData['admin_id'] = Auth::id();
                $orderData['order_status'] = 'confirmed';
                $orderData['is_customer_create'] = '0';
            }

            // အော်ဒါဖန်တီးခြင်း
            $order = Order::create($orderData);

            // ပစ္စည်းများထည့်သွင်းခြင်း
            if ($request->has('items') && is_array($payload['items'])) {
                $this->assignOrderItems($order, $payload['items'], $userType);
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
            $order = Order::when($userType === 'user', function ($q) {
                $q->where('user_id', Auth::user()->id);
            })
                ->when($userType === 'admin', function ($q) {
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
        $cartIdsToDelete = []; // Initialize here, not inside the loop

        foreach ($items as $item) {
            $individualPricing = null;
            if (isset($item['individual_pricing'])) {
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

            // Handle Hotel products
            if ($orderItem['product_type'] == Hotel::class) {
                $hotel = Hotel::find($item['product_id']);

                // Check if hotel exists
                if ($hotel) {
                    // Check if hotel has partners
                    $partner = $hotel->partners->first();

                    if ($partner && !empty($item['room_id']) && !empty($item['checkin_date']) && !empty($item['checkout_date'])) {
                        try {
                            $roomRateService = new PartnerRoomRateService($partner->id, $item['room_id']);
                            $room_rates = $roomRateService->getRateForDaterange(
                                $item['checkin_date'],
                                $item['checkout_date']
                            );

                            $orderItem['room_rates'] = $room_rates;
                            $orderItem['incomplete_allotment'] = $roomRateService->isIncompleteAllotment(
                                $item['checkin_date'],
                                $item['checkout_date']
                            );
                        } catch (\Exception $e) {
                            Log::error($e);
                            // Continue without room_rates and incomplete_allotment
                        }
                    } else {
                        // Log the reason for skipping
                        if (!$partner) {
                            Log::info('Hotel has no partner - skipping room rates', ['hotel_id' => $hotel->id]);
                        } else {
                            Log::info('Missing required data for room rates', [
                                'hotel_id' => $hotel->id,
                                'has_room_id' => !empty($item['room_id']),
                                'has_checkin' => !empty($item['checkin_date']),
                                'has_checkout' => !empty($item['checkout_date'])
                            ]);
                        }
                    }
                } else {
                    Log::warning('Hotel not found', ['product_id' => $item['product_id']]);
                }
            }

            $order->items()->save($orderItem);
            $createdItems[] = $orderItem;

            // Collect cart IDs to delete
            if (isset($item['cart_id'])) {
                $cartIdsToDelete[] = $item['cart_id'];
            }
        }

        // Clean up cart items
        if (!empty($cartIdsToDelete) && Auth::check()) {
            try {
                Cart::where('owner_id', Auth::id())
                    ->where('owner_type', get_class(Auth::user()))
                    ->whereIn('id', $cartIdsToDelete)
                    ->delete();

                // Log::info('Cart cleaned', ['deleted_count' => count($cartIdsToDelete)]);
            } catch (\Exception $e) {
                Log::error('Failed to delete cart items', [
                    'error' => $e->getMessage(),
                    'cart_ids' => $cartIdsToDelete
                ]);
            }
        }

        return $createdItems;
    }

    public function show(Request $request, $id)
    {
        $userType = $request->query('type', 'user'); // Default to 'user'

        $order = Order::with(['items', 'customer', 'admin', 'payments', 'booking', 'user'])
            ->when($userType === 'user', function ($q) {
                $q->where('user_id', Auth::id());
            })
            ->when($userType === 'admin', function ($q) {
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

        $order = Order::when($userType === 'user', function ($q) {
            $q->where('user_id', Auth::id());
        })
            ->when($userType === 'admin', function ($q) {
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
