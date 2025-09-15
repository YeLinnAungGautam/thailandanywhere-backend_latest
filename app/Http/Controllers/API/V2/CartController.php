<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Hotel;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    use HttpResponses;

    // List all cart items
    public function index(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $cartItems = Cart::with('product')
            ->where('owner_id', Auth::id())
            ->where('owner_type', get_class(Auth::user()))
            ->when($request->selected_ids, function ($query) use ($request) {
                $query->whereIn('id', explode(',', $request->selected_ids));
            })
            ->get();

        $cartCollection = CartResource::collection($cartItems);

        $balance_due_items = [];
        foreach ($cartCollection as $item) {
            $cart_data = $item->resolve();

            $balance_due_items[] = [
                'name' => $item->product->name,
                'variation_name' => $item->variation->name ?? null,
                'service_days' => $cart_data['service_days'] ?? null,
                'quantity' => $item->quantity,
                'price' => $item->variation->room_price ?? 0,
                'selling_price' => $cart_data['options']['total_selling_price'] ?? 0,
                'discount' => $cart_data['options']['discount'] ?? 0,
                'cost_price' => $cart_data['options']['total_selling_price'] - ($cart_data['options']['discount'] ?? 0),
            ];
        }

        return $cartCollection->additional([
            'meta' => [
                'balance_due_items' => $balance_due_items,
                'sub_total' => array_sum(array_column($balance_due_items, 'selling_price')),
                'online_discount' => array_sum(array_column($balance_due_items, 'discount')),
                'grand_total' => array_sum(array_column($balance_due_items, 'cost_price')),
            ],
        ]);
    }

    // Show single cart item
    public function show(Cart $cart)
    {
        if ($cart->owner_id !== Auth::id() || $cart->owner_type !== get_class(Auth::user())) {
            return $this->error(null, 'Unauthorized', 403);
        }

        return $this->success(CartResource::make($cart), 'Cart item fetched successfully');
    }


    // Add to cart
    public function store(Request $request)
    {
        try {
            $input = $this->validateCartRequest($request);
            $validated = $this->calculateOptions($input);

            $owner = Auth::user();

            $cartItem = Cart::create([
                'owner_id' => $owner->id,
                'owner_type' => get_class($owner),
                ...$validated
            ]);

            return $this->success(CartResource::make($cartItem->fresh()), 'Cart item added successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to add item to cart: ' . $e->getMessage(), 500);
        }
    }

    // Update cart item
    public function update(Request $request, Cart $cart)
    {
        try {
            if ($cart->owner_id !== Auth::id() || $cart->owner_type !== get_class(Auth::user())) {
                return $this->error(null, 'Unauthorized', 403);
            }

            $input = $this->validateCartRequest($request);
            $validated = $this->calculateOptions($input);

            $cart->update($validated);

            return $this->success(CartResource::make($cart->fresh()), 'Cart item updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update cart item: ' . $e->getMessage(), 500);
        }
    }

    // Remove from cart
    public function destroy(Cart $cart)
    {

        if ($cart->owner_id !== Auth::id() || $cart->owner_type !== get_class(Auth::user())) {
            return $this->error(null, 'Unauthorized', 403);
        }

        $cart->delete();

        return $this->success(null, 'Cart item removed successfully');
    }

    // Clear entire cart
    public function clear()
    {
        Cart::where('owner_id', Auth::id())->delete();

        return $this->success(null, 'Cart cleared successfully');
    }

    // Validation rules
    protected function validateCartRequest(Request $request)
    {
        return $request->validate([
            'product_id' => 'required|integer',
            'product_type' => [
                'required',
                Rule::in([
                    'App\Models\Hotel',
                    'App\Models\EntranceTicket',
                    'App\Models\PrivateVanTour',
                    'App\Models\Inclusive'
                ])
            ],
            'variation_id' => [
                Rule::requiredIf(function () use ($request) {
                    return $request->product_type !== 'App\Models\Inclusive';
                }),
                'nullable',
                'integer'
            ],
            'quantity' => 'required|integer|min:1|max:100',
            'service_date' => [
                Rule::requiredIf(function () use ($request) {
                    return in_array($request->product_type, [
                        'App\Models\EntranceTicket',
                        'App\Models\PrivateVanTour',
                        'App\Models\Inclusive'
                    ]);
                }),
                'nullable',
                'date',
                'after_or_equal:today'
            ],
            'checkout_date' => [
                Rule::requiredIf($request->product_type === 'App\Models\Hotel'),
                'nullable',
                'date',
                'after:service_date'
            ],
            'options' => 'nullable|array',
            'is_calculated' => 'nullable|boolean',
        ]);
    }

    private function calculateOptions(array $validated)
    {
        if (isset($validated['is_calculated']) && $validated['is_calculated'] == false && $validated['product_type'] === Hotel::class) {
            $stay_nights = Carbon::parse($validated['service_date'])->diffInDays(Carbon::parse($validated['checkout_date']));

            $validated['options']['selling_price'] = ($validated['options']['selling_price'] ?? 0) * $stay_nights * $validated['quantity'];
            $validated['options']['total_selling_price'] = ($validated['options']['total_selling_price'] ?? 0) * $stay_nights * $validated['quantity'];
            $validated['options']['cost_price'] = ($validated['options']['cost_price'] ?? 0) * $stay_nights * $validated['quantity'];
            $validated['options']['total_cost_price'] = ($validated['options']['total_cost_price'] ?? 0) * $stay_nights * $validated['quantity'];
        }

        return $validated;
    }
}
