<?php
namespace App\Services;

use App\Models\Cart;
use Carbon\Carbon;

class OrderManager
{
    public static function formatMobileOrderData(array $orderData)
    {
        $order_items = explode(',', $orderData['items'] ?? '');

        $carts = Cart::query()
            ->whereIn('id', $order_items)
            ->get();

        $items = $carts->map(function ($cart) {
            $cart_options = $cart->options;

            return [
                "cart_id" => $cart->id,
                "product_id" => $cart->product_id,
                "product_type" => $cart->product_type,
                "quantity" => $cart->quantity,
                "selling_price" => $cart_options['selling_price'] ?? 0,
                "total_selling_price" => $cart_options['total_selling_price'] ?? 0,
                "service_date" => Carbon::parse($cart->service_date)->format('Y-m-d'),
                "room_id" => $cart->variation_id,
                "checkin_date" => Carbon::parse($cart->service_date)->format('Y-m-d'),
                "checkout_date" => Carbon::parse($cart->checkout_date)->format('Y-m-d'),
                "cost_price" => $cart_options['cost_price'] ?? 0,
                "total_cost_price" => $cart_options['total_cost_price'] ?? 0,
                "discount" => $cart_options['discount'] ?? 0,
                "special_request" => $cart_options['special_request'] ?? ""
            ];
        })->toArray();

        $earliest_service_date = collect($items)->min('service_date');
        $balance_due_date = $earliest_service_date
            ? \Carbon\Carbon::parse($earliest_service_date)->subDay()->toDateString()
            : null;

        $total_discount = array_sum(array_column($items, 'discount'));
        $total_selling_price = array_sum(array_column($items, 'total_selling_price'));
        $grand_total = $total_selling_price - $total_discount;

        $result = [
            "email" => $orderData['email'],
            "customer_name" => $orderData['customer_name'],
            "type" => $orderData['type'] ?? 'user',
            'admin_id' => $orderData['admin_id'],
            "sold_from" => $orderData['sold_from'] ?? 'mobile',
            "phone_number" => $orderData['phone_number'] ?? null,
            "discount" => $total_discount,
            "total_amount" => $total_selling_price,
            "grand_total" => $grand_total,
            "balance_due_date" => $balance_due_date,
            'items' => $items,
        ];

        return $result;
    }
}
