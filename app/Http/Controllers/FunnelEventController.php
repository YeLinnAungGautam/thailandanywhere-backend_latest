<?php

namespace App\Http\Controllers;

use App\Models\FunnelEvent;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FunnelEventController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $analytics = $this->getFunnelAnalytics($startDate, $endDate);

        return $this->success($analytics, 'Funnel analytics retrieved successfully');
    }

    public function getFunnelAnalytics($startDate, $endDate)
    {
        // Base query with date range
        $baseQuery = FunnelEvent::dateRange($startDate, $endDate);

        // Total visit count
        $totalVisits = (clone $baseQuery)->visits()->count();

        // Visit count by product type
        $visitsByProductType = (clone $baseQuery)
            ->visits()
            ->select('product_type', DB::raw('count(*) as count'))
            ->whereNotNull('product_type')
            ->groupBy('product_type')
            ->get()
            ->pluck('count', 'product_type')
            ->toArray();



        // View detail count
        $viewDetailCount = (clone $baseQuery)->views()->count();

        // View detail by product type
        $viewsByProductType = (clone $baseQuery)
            ->views()
            ->select('product_type', DB::raw('count(*) as count'))
            ->whereNotNull('product_type')
            ->groupBy('product_type')
            ->get()
            ->pluck('count', 'product_type')
            ->toArray();

        // Add to cart count
        $addToCartCount = (clone $baseQuery)->cartAdds()->count();

        // Add to cart by product type
        $cartAddsByProductType = (clone $baseQuery)
            ->cartAdds()
            ->select('product_type', DB::raw('count(*) as count'))
            ->whereNotNull('product_type')
            ->groupBy('product_type')
            ->get()
            ->pluck('count', 'product_type')
            ->toArray();

        // Go checkout count
        $goCheckoutCount = (clone $baseQuery)->checkouts()->count();

        // Checkout by product type
        $checkoutsByProductType = (clone $baseQuery)
            ->checkouts()
            ->select('product_type', DB::raw('count(*) as count'))
            ->whereNotNull('product_type')
            ->groupBy('product_type')
            ->get()
            ->pluck('count', 'product_type')
            ->toArray();

        // Complete purchase count
        $completePurchaseCount = (clone $baseQuery)->purchases()->count();

        // Purchase by product type
        $purchasesByProductType = (clone $baseQuery)
            ->purchases()
            ->select('product_type', DB::raw('count(*) as count'))
            ->whereNotNull('product_type')
            ->groupBy('product_type')
            ->get()
            ->pluck('count', 'product_type')
            ->toArray();

        // Calculate conversion rates
        $conversionRates = [
            'visit_to_view' => $totalVisits > 0 ? round(($viewDetailCount / $totalVisits) * 100, 2) : 0,
            'view_to_cart' => $viewDetailCount > 0 ? round(($addToCartCount / $viewDetailCount) * 100, 2) : 0,
            'cart_to_checkout' => $addToCartCount > 0 ? round(($goCheckoutCount / $addToCartCount) * 100, 2) : 0,
            'checkout_to_purchase' => $goCheckoutCount > 0 ? round(($completePurchaseCount / $goCheckoutCount) * 100, 2) : 0,
            'overall' => $totalVisits > 0 ? round(($completePurchaseCount / $totalVisits) * 100, 2) : 0,
        ];

        return [
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'total_counts' => [
                'visits' => $totalVisits,
                'view_details' => $viewDetailCount,
                'add_to_cart' => $addToCartCount,
                'go_checkout' => $goCheckoutCount,
                'complete_purchase' => $completePurchaseCount,
            ],
            'visits_by_product_type' => $visitsByProductType,
            'views_by_product_type' => $viewsByProductType,
            'cart_adds_by_product_type' => $cartAddsByProductType,
            'checkouts_by_product_type' => $checkoutsByProductType,
            'purchases_by_product_type' => $purchasesByProductType,
            'conversion_rates' => $conversionRates,
        ];
    }

    // Alternative: Get funnel for specific product type
    public function getProductTypeFunnel(Request $request, string $productType)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $baseQuery = FunnelEvent::dateRange($startDate, $endDate)
            ->byProductType($productType);

        $data = [
            'product_type' => $productType,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'funnel' => [
                'visits' => (clone $baseQuery)->visits()->count(),
                'view_details' => (clone $baseQuery)->views()->count(),
                'add_to_cart' => (clone $baseQuery)->cartAdds()->count(),
                'go_checkout' => (clone $baseQuery)->checkouts()->count(),
                'complete_purchase' => (clone $baseQuery)->purchases()->count(),
            ],
        ];

        return $this->success($data, "Funnel data for {$productType} retrieved successfully");
    }
}
