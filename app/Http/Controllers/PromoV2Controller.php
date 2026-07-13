<?php

namespace App\Http\Controllers;

use App\Models\BookingItem;
use App\Models\Promo;
use App\Models\PromoUsage;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromoV2Controller extends Controller
{
    use HttpResponses;
    // GET /api/v2/promos/search?name=... - customer searches promo by name
    public function search(Request $request)
    {
        $request->validate(['name' => 'required|string']);

        $promos = Promo::where('promo_name', 'like', '%' . $request->name . '%')
            ->where('promo_active', true)
            ->get()
            ->filter(fn ($promo) => $promo->isValid())
            ->values();

        return response()->json($promos);
    }

    // POST /api/v2/promos/apply - apply coupon to a specific booking item
    public function apply(Request $request)
    {
        $request->validate([
            'promo_code'      => 'required|string',
            'booking_item_id' => 'required|exists:booking_items,id',
        ]);

        $promo = Promo::where('promo_code', $request->promo_code)->first();

        if (! $promo) {
            return response()->json(['message' => 'Invalid coupon code.'], 404);
        }
        if (! $promo->promo_active) {
            return response()->json(['message' => 'This coupon is no longer active.'], 422);
        }
        if (! $promo->isWithinDateRange()) {
            return response()->json(['message' => 'This coupon has expired or is not yet active.'], 422);
        }
        if (! $promo->hasUsesLeft()) {
            return response()->json(['message' => 'This coupon has reached its usage limit.'], 422);
        }

        $bookingItem = BookingItem::findOrFail($request->booking_item_id);

        if (! $promo->isApplicableToItem($bookingItem->product_type, $bookingItem->product_id)) {
            return response()->json([
                'message' => 'This coupon is not valid for this item.',
            ], 422);
        }

        if ($bookingItem->promo_id) {
            return response()->json([
                'message' => 'This item already has a coupon applied. Remove it first.',
            ], 422);
        }

        $result = DB::transaction(function () use ($promo, $bookingItem, $request) {
            $lockedPromo = Promo::where('promo_id', $promo->promo_id)->lockForUpdate()->first();

            if (! $lockedPromo->hasUsesLeft()) {
                abort(422, 'This coupon has reached its usage limit.');
            }

            $discount = $lockedPromo->calculateDiscount((float) $bookingItem->amount);

            $bookingItem->promo_id = $lockedPromo->promo_id;
            $bookingItem->discount_amount = $discount;
            $bookingItem->save();

            $lockedPromo->increment('promo_used_count');

            PromoUsage::create([
                'promo_id'         => $lockedPromo->promo_id,
                'booking_item_id'  => $bookingItem->id,
                'customer_id'          => $request->user()?->id,
                'discount_applied' => $discount,
            ]);

            return [
                'discount_amount' => $discount,
                'booking_item'    => $bookingItem,
            ];
        });

        return response()->json([
            'message' => 'Coupon applied successfully.',
            'data'    => $result,
        ]);
    }

    // POST /api/v2/promos/remove - remove coupon from a booking item
    public function remove(Request $request)
    {
        $request->validate(['booking_item_id' => 'required|exists:booking_items,id']);

        $bookingItem = BookingItem::findOrFail($request->booking_item_id);

        if ($bookingItem->promo_id) {
            DB::transaction(function () use ($bookingItem) {
                $promo = Promo::where('promo_id', $bookingItem->promo_id)->lockForUpdate()->first();
                $promo?->decrement('promo_used_count');

                PromoUsage::where('booking_item_id', $bookingItem->id)
                    ->where('promo_id', $bookingItem->promo_id)
                    ->delete();

                $bookingItem->promo_id = null;
                $bookingItem->discount_amount = 0;
                $bookingItem->save();
            });
        }

        return response()->json(['message' => 'Coupon removed.']);
    }
}
