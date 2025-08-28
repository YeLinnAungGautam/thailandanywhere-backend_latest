<?php

namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Models\BookingItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get monthly sales graph data for a specific product
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMonthlySalesGraph(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'product_id' => 'required|integer',
            'product_type' => 'required|string'
        ]);

        $year = $request->year;
        $productId = $request->product_id;
        $productType = $request->product_type;

        try {
            // Get monthly sales data with total income (only fully_paid bookings)
            $monthlySales = BookingItem::select(
                DB::raw('MONTH(service_date) as month'),
                DB::raw('SUM(
                    CASE
                        WHEN checkin_date IS NOT NULL AND checkout_date IS NOT NULL
                        THEN quantity * DATEDIFF(checkout_date, checkin_date)
                        ELSE quantity
                    END
                ) as total_quantity'),
                DB::raw('COUNT(*) as total_items'),
                DB::raw('SUM(total_cost_price) as total_income')
            )
            ->where('product_id', $productId)
            ->where('product_type', $productType)
            ->where('payment_status', 'fully_paid')
            ->whereYear('service_date', $year)
            ->whereNull('deleted_at')
            ->groupBy(DB::raw('MONTH(service_date)'))
            ->orderBy('month')
            ->get();

            // Initialize all months with zero values
            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthlyData[] = [
                    'month' => $month,
                    'month_name' => Carbon::create()->month($month)->format('M'),
                    'total_quantity' => 0,
                    'total_items' => 0,
                    'total_income' => 0
                ];
            }

            // Fill in the actual data
            foreach ($monthlySales as $sale) {
                $monthIndex = $sale->month - 1;
                $monthlyData[$monthIndex]['total_quantity'] = (int) $sale->total_quantity;
                $monthlyData[$monthIndex]['total_items'] = (int) $sale->total_items;
                $monthlyData[$monthIndex]['total_income'] = (float) $sale->total_income;
            }

            // Get total unique bookings count (distinct group_id) for the year (only fully_paid)
            $totalUniqueBookings = BookingItem::where('product_id', $productId)
                ->where('product_type', $productType)
                ->where('payment_status', 'fully_paid')
                ->whereYear('service_date', $year)
                ->whereNull('deleted_at')
                ->distinct('group_id')
                ->count();

            // Get today's booking count (distinct group_id for today) (only fully_paid)
            $todayBookingCount = BookingItem::where('product_id', $productId)
                ->where('product_type', $productType)
                ->where('payment_status', 'fully_paid')
                ->whereDate('service_date', Carbon::today())
                ->whereNull('deleted_at')
                ->distinct('group_id')
                ->count();

            return response()->json([
                'status' => 1,
                'message' => 'Monthly sales data retrieved successfully',
                'data' => [
                    'year' => $year,
                    'product_id' => $productId,
                    'product_type' => $productType,
                    'monthly_sales' => $monthlyData,
                    'total_year_quantity' => array_sum(array_column($monthlyData, 'total_quantity')),
                    'total_year_items' => array_sum(array_column($monthlyData, 'total_items')),
                    'total_year_income' => array_sum(array_column($monthlyData, 'total_income')),
                    'total_unique_bookings' => $totalUniqueBookings,
                    'today_booking_count' => $todayBookingCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving monthly sales data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get most selling rooms data for hotels
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
}
