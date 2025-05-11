<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    use HttpResponses;
    // List all cart items
    public function index()
    {
        $cartItems = Cart::with(['product', 'variation'])
            ->where('user_id', Auth::id())
            ->get();

        return CartResource::collection($cartItems);
    }

    // Show single cart item
    public function show(Cart $cart)
    {
        $this->authorize('view', $cart);

        return $this->success(CartResource::make($cart->load(['product', 'variation'])), 'Cart item fetched successfully');
    }

    // Add to cart
    public function store(Request $request)
    {
        $validated = $this->validateCartRequest($request);

        $existingItem = $this->findExistingCartItem($validated);

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $existingItem->quantity + $validated['quantity']
            ]);

            return new CartResource($existingItem->fresh());
        }

        $cartItem = Cart::create([
            'user_id' => Auth::id(),
            ...$validated
        ]);

        return $this->success(CartResource::make($cartItem->fresh()), 'Cart item added successfully');
    }

    // Update cart item
    public function update(Request $request, Cart $cart)
    {
        $this->authorize('update', $cart);

        $validated = $this->validateCartRequest($request);

        $cart->update($validated);

        return $this->success(CartResource::make($cart->fresh()), 'Cart item updated successfully');
    }

    // Remove from cart
    public function destroy(Cart $cart)
    {
        $this->authorize('delete', $cart);

        $cart->delete();

        return $this->success(null, 'Cart item removed successfully');
    }

    // Clear entire cart
    public function clear()
    {
        Cart::where('user_id', Auth::id())->delete();

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
            'options' => 'nullable|array'
        ]);
    }

    // Find existing cart item
    protected function findExistingCartItem(array $validated)
    {
        $query = Cart::where('user_id', Auth::id())
            ->where('product_id', $validated['product_id'])
            ->where('product_type', $validated['product_type']);

        // Inclusive မဟုတ်တဲ့ products အတွက် variation_id စစ်ဆေးခြင်း
        if ($validated['product_type'] !== 'App\Models\Inclusive' && isset($validated['variation_id'])) {
            $query->where('variation_id', $validated['variation_id']);
        }

        // EntranceTicket, PrivateVanTour, Inclusive တို့အတွက် service_date စစ်ဆေးခြင်း
        if (in_array($validated['product_type'], [
            'App\Models\EntranceTicket',
            'App\Models\PrivateVanTour',
            'App\Models\Inclusive'
        ]) && isset($validated['service_date'])) {
            $query->where('service_date', $validated['service_date']);
        }

        // Hotel products များအတွက် checkout_date စစ်ဆေးခြင်း
        if ($validated['product_type'] === 'App\Models\Hotel' && isset($validated['checkout_date'])) {
            $query->where('checkout_date', $validated['checkout_date']);
        }

        // Options များကိုစစ်ဆေးခြင်း
        if (isset($validated['options'])) {
            $query->where('options', json_encode($validated['options']));
        }

        return $query->first();
    }
}
