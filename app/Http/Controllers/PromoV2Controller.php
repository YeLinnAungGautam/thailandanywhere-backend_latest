<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PromoV2Controller extends Controller
{
    use HttpResponses;

    // GET /api/v2/promos/search?name=... - customer searches promo by name
    public function search(Request $request)
    {
        $request->validate(['name' => 'nullable|string']);

        $promos = Promo::where('promo_name', 'like', '%' . $request->name . '%')
            ->where('promo_active', true)
            ->get()
            ->filter(fn ($promo) => $promo->isValid())
            ->values();

        return $this->success($promos, 'Promo list');
    }

    /**
     * GET /api/v2/promos/search-by-date?date=2026-07-20
     *
     * Simple search: one date, return active/usable promos whose
     * validity window covers that date.
     */
    public function searchByDate(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($request->date);

        $promos = Promo::where('promo_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('promo_start_date')
                  ->orWhere('promo_start_date', '<=', $date);
            })
            ->where('promo_end_date', '>=', $date)
            ->get()
            ->filter(fn ($promo) => $promo->isValid($date))
            ->values();

        return $this->success($promos, 'Promo list');
    }

    /**
     * GET /api/v2/promos/available
     *   ?date=2026-07-20                          (single date)
     *   or ?start_date=2026-07-20&end_date=2026-07-25   (date range)
     *   &product_type=hotel|entrance_ticket|vantour|inclusive|airline|airport_pickup
     *   &product_id=123
     *
     * Returns promos that:
     *  - are active, within their window, and still have uses left
     *  - are applicable to the given product (via isApplicableToItem)
     */
    public function searchAvailable(Request $request)
    {
        $validated = $request->validate([
            'date'         => 'nullable|date',
            'start_date'   => 'nullable|date|required_without:date',
            'end_date'     => 'nullable|date|required_without:date|after_or_equal:start_date',
            'product_type' => 'required|in:' . implode(',', array_keys(Promo::PRODUCT_TYPES)),
            'product_id'   => 'required|integer',
        ]);

        $rangeStart = Carbon::parse($validated['start_date'] ?? $validated['date']);
        $rangeEnd   = Carbon::parse($validated['end_date'] ?? $validated['date']);

        $productClass = Promo::PRODUCT_TYPES[$validated['product_type']];
        $productId    = (int) $validated['product_id'];

        $promos = Promo::where('promo_active', true)
            // promo's own window must cover the whole requested range
            ->where(function ($q) use ($rangeStart) {
                $q->whereNull('promo_start_date')
                  ->orWhere('promo_start_date', '<=', $rangeStart);
            })
            ->where('promo_end_date', '>=', $rangeEnd)
            ->get()
            ->filter(function ($promo) use ($rangeStart, $rangeEnd, $productClass, $productId) {
                // must be valid for both ends of the range (covers usage/active too)
                if (! $promo->isValid($rangeStart) || ! $promo->isValid($rangeEnd)) {
                    return false;
                }

                return $promo->isApplicableToItem($productClass, $productId);
            })
            ->values();

        return $this->success($promos, 'Available promo list');
    }
}
