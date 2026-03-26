<?php

namespace App\Http\Controllers;

use App\Models\CommissionTier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommissionTierController extends Controller
{
    /**
     * GET /api/commission-tiers
     * Return all active tiers ordered by min_salary (used by Vue frontend).
     */
    public function index()
    {
        $tiers = CommissionTier::active()->get();

        return response()->json([
            'data'    => $tiers,
            'message' => 'Commission tiers fetched successfully.',
        ]);
    }

    /**
     * POST /api/commission-tiers
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'label'       => ['required', 'string', 'max:50', Rule::unique('commission_tiers', 'label')],
            'min_salary'  => ['required', 'integer', 'min:0'],
            'avg_daily'   => ['required', 'integer', 'min:0'],
            'rate'        => ['required', 'numeric', 'min:0'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $tier = CommissionTier::create($validated);

        return response()->json([
            'data'    => $tier,
            'message' => 'Commission tier created successfully.',
        ], 201);
    }

    /**
     * GET /api/commission-tiers/{id}
     */
    public function show(CommissionTier $commissionTier)
    {
        return response()->json([
            'data'    => $commissionTier,
            'message' => 'Commission tier fetched successfully.',
        ]);
    }

    /**
     * PUT /api/commission-tiers/{id}
     */
    public function update(Request $request, CommissionTier $commissionTier)
    {
        $validated = $request->validate([
            'label'       => ['sometimes', 'string', 'max:50', Rule::unique('commission_tiers', 'label')->ignore($commissionTier->id)],
            'min_salary'  => ['sometimes', 'integer', 'min:0'],
            'avg_daily'   => ['sometimes', 'integer', 'min:0'],
            'rate'        => ['sometimes', 'numeric', 'min:0'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $commissionTier->update($validated);

        return response()->json([
            'data'    => $commissionTier->fresh(),
            'message' => 'Commission tier updated successfully.',
        ]);
    }

    /**
     * DELETE /api/commission-tiers/{id}
     */
    public function destroy(CommissionTier $commissionTier)
    {
        $commissionTier->delete();

        return response()->json([
            'message' => 'Commission tier deleted successfully.',
        ]);
    }

    /**
     * POST /api/commission-tiers/calculate
     * Given an average daily amount, return the matching tier + commission.
     *
     * Body: { "amount": 42000 }
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $amount = $request->input('amount');

        $tier = CommissionTier::active()
            ->where('min_salary', '<=', $amount)
            ->orderByDesc('min_salary')
            ->first();

        return response()->json([
            'data' => [
                'amount'     => $amount,
                'tier'       => $tier,
                'commission' => $tier ? "{$tier->rate} lakh MMK" : '0',
            ],
            'message' => $tier ? 'Tier matched.' : 'No tier matched for this amount.',
        ]);
    }
}
