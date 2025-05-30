<?php

namespace App\Http\Controllers\API\V2;

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
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $cartItems = Cart::with('product')
            ->where('owner_id', Auth::id())
            ->where('owner_type', get_class(Auth::user()))
            ->get();

        return CartResource::collection($cartItems);
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
            $validated = $this->validateCartRequest($request);

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

            $validated = $this->validateCartRequest($request);

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
}
