<?php

namespace App\Http\Controllers;

use App\Models\FunnelEvent;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
        $baseQuery = FunnelEvent::dateRange($startDate, $endDate);

        // Total counts
        $totalVisits = (clone $baseQuery)->visits()->count();
        $viewDetailCount = (clone $baseQuery)->views()->count();
        $addToCartCount = (clone $baseQuery)->cartAdds()->count();
        $goCheckoutCount = (clone $baseQuery)->checkouts()->count();
        $completePurchaseCount = (clone $baseQuery)->purchases()->count();
        $messengerClickCount = (clone $baseQuery)->where('event_type', 'messenger_click')->count();

        // By product type
        $visitsByProductType = (clone $baseQuery)->visits()
            ->select('product_type', DB::raw('count(*) as count'))
            ->whereNotNull('product_type')
            ->groupBy('product_type')
            ->get()->pluck('count', 'product_type')->toArray();

        $viewsByProductType = (clone $baseQuery)->views()
            ->select('product_type', DB::raw('count(*) as count'))
            ->whereNotNull('product_type')
            ->groupBy('product_type')
            ->get()->pluck('count', 'product_type')->toArray();

        $cartAddsByProductType = (clone $baseQuery)->cartAdds()
            ->select('product_type', DB::raw('count(*) as count'))
            ->whereNotNull('product_type')
            ->groupBy('product_type')
            ->get()->pluck('count', 'product_type')->toArray();

        $checkoutsByProductType = (clone $baseQuery)->checkouts()
            ->select('product_type', DB::raw('count(*) as count'))
            ->whereNotNull('product_type')
            ->groupBy('product_type')
            ->get()->pluck('count', 'product_type')->toArray();

        $purchasesByProductType = (clone $baseQuery)->purchases()
            ->select('product_type', DB::raw('count(*) as count'))
            ->whereNotNull('product_type')
            ->groupBy('product_type')
            ->get()->pluck('count', 'product_type')->toArray();

        $messengerClicksByProductType = (clone $baseQuery)
            ->where('event_type', 'messenger_click')
            ->select('product_type', DB::raw('count(*) as count'))
            ->whereNotNull('product_type')
            ->groupBy('product_type')
            ->get()->pluck('count', 'product_type')->toArray();

        // Conversion rates
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
                'messenger_click' => $messengerClickCount,
            ],
            'visits_by_product_type' => $visitsByProductType,
            'views_by_product_type' => $viewsByProductType,
            'cart_adds_by_product_type' => $cartAddsByProductType,
            'checkouts_by_product_type' => $checkoutsByProductType,
            'purchases_by_product_type' => $purchasesByProductType,
            'messenger_clicks_by_product_type' => $messengerClicksByProductType,
            'conversion_rates' => $conversionRates,
        ];
    }

    /**
     * Get time series data for graphs
     */
    public function getTimeSeries(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'event_type' => 'required|in:visit_site,view_detail,add_to_cart,go_checkout,complete_purchase,messenger_click',
            'granularity' => 'required|in:daily,weekly,monthly',
            'product_type' => 'nullable|in:hotel,entrance_ticket,private_van_tour',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $eventType = $request->event_type;
        $granularity = $request->granularity;
        $productType = $request->product_type;

        $query = FunnelEvent::whereBetween('created_at', [$startDate, $endDate])
            ->where('event_type', $eventType);

        if ($productType) {
            $query->where('product_type', $productType);
        }

        $data = [];

        switch ($granularity) {
            case 'daily':
                $data = $this->getDailyData($query, $startDate, $endDate);
                break;
            case 'weekly':
                $data = $this->getWeeklyData($query, $startDate, $endDate);
                break;
            case 'monthly':
                $data = $this->getMonthlyData($query, $startDate, $endDate);
                break;
        }

        return $this->success([
            'event_type' => $eventType,
            'granularity' => $granularity,
            'product_type' => $productType,
            'data' => $data,
        ], 'Time series data retrieved successfully');
    }

    private function getDailyData($query, $startDate, $endDate)
    {
        $results = $query
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $data = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $data[] = [
                'date' => $dateStr,
                'label' => $currentDate->format('M d'),
                'count' => $results->get($dateStr)->count ?? 0,
            ];
            $currentDate->addDay();
        }

        return $data;
    }

    private function getWeeklyData($query, $startDate, $endDate)
    {
        $results = $query
            ->select(
                DB::raw('YEARWEEK(created_at, 1) as week'),
                DB::raw('MIN(DATE(created_at)) as week_start'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('week')
            ->orderBy('week')
            ->get()
            ->keyBy('week');

        $data = [];
        $currentDate = $startDate->copy()->startOfWeek();

        while ($currentDate->lte($endDate)) {
            $weekNumber = $currentDate->format('oW');
            $weekStart = $currentDate->copy();
            $weekEnd = $currentDate->copy()->endOfWeek();

            $data[] = [
                'date' => $weekStart->format('Y-m-d'),
                'label' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
                'count' => $results->get($weekNumber)->count ?? 0,
            ];

            $currentDate->addWeek();
        }

        return $data;
    }

    private function getMonthlyData($query, $startDate, $endDate)
    {
        $results = $query
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $data = [];
        $currentDate = $startDate->copy()->startOfMonth();

        while ($currentDate->lte($endDate)) {
            $monthStr = $currentDate->format('Y-m');
            $data[] = [
                'date' => $monthStr,
                'label' => $currentDate->format('M Y'),
                'count' => $results->get($monthStr)->count ?? 0,
            ];
            $currentDate->addMonth();
        }

        return $data;
    }

    /**
     * Get product type funnel
     */
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
                'messenger_click' => (clone $baseQuery)->where('event_type', 'messenger_click')->count(),
            ],
        ];

        return $this->success($data, "Funnel data for {$productType} retrieved successfully");
    }
}
