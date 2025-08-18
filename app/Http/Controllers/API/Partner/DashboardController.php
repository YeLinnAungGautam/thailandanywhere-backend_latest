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
            // Get monthly sales data
            $monthlySales = BookingItem::select(
                DB::raw('MONTH(service_date) as month'),
                DB::raw('SUM(
                    CASE
                        WHEN checkin_date IS NOT NULL AND checkout_date IS NOT NULL
                        THEN quantity * DATEDIFF(checkout_date, checkin_date)
                        ELSE quantity
                    END
                ) as total_quantity'),
                DB::raw('COUNT(*) as total_items')
            )
            ->where('product_id', $productId)
            ->where('product_type', $productType)
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
                    'total_items' => 0
                ];
            }

            // Fill in the actual data
            foreach ($monthlySales as $sale) {
                $monthIndex = $sale->month - 1;
                $monthlyData[$monthIndex]['total_quantity'] = (int) $sale->total_quantity;
                $monthlyData[$monthIndex]['total_items'] = (int) $sale->total_items;
            }

            return response()->json([
                'status' => 1,
                'message' => 'Monthly sales data retrieved successfully',
                'data' => [
                    'year' => $year,
                    'product_id' => $productId,
                    'product_type' => $productType,
                    'monthly_sales' => $monthlyData,
                    'total_year_quantity' => array_sum(array_column($monthlyData, 'total_quantity')),
                    'total_year_items' => array_sum(array_column($monthlyData, 'total_items'))
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
    public function getMostSellingRooms(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'product_id' => 'required|integer',
            'product_type' => 'required|string',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        $year = $request->year;
        $productId = $request->product_id;
        $productType = $request->product_type;
        $limit = $request->limit ?? 10;

        try {
            // Check if the product type is Hotel
            if (!str_contains($productType, 'Hotel')) {
                return response()->json([
                    'success' => false,
                    'message' => 'This endpoint is only available for Hotel product types',
                    'data' => []
                ], 400);
            }

            // Get most selling rooms data
            $mostSellingRooms = BookingItem::select(
                'room_id',
                DB::raw('SUM(
                    CASE
                        WHEN checkin_date IS NOT NULL AND checkout_date IS NOT NULL
                        THEN quantity * DATEDIFF(checkout_date, checkin_date)
                        ELSE quantity
                    END
                ) as total_quantity'),
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw('SUM(CAST(selling_price as DECIMAL(10,2))) as total_revenue'),
                DB::raw('AVG(CAST(selling_price as DECIMAL(10,2))) as avg_selling_price')
            )
            ->with(['room' => function($query) {
                $query->withTrashed()->select('id', 'name', 'room_type', 'capacity');
            }])
            ->where('product_id', $productId)
            ->where('product_type', $productType)
            ->whereYear('service_date', $year)
            ->whereNotNull('room_id')
            ->whereNull('deleted_at')
            ->groupBy('room_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

            // Format the response data
            $formattedRooms = $mostSellingRooms->map(function ($item) {
                return [
                    'room_id' => $item->room_id,
                    'room_name' => $item->room ? $item->room->name : 'Unknown Room',
                    'room_type' => $item->room ? $item->room->room_type : null,
                    'room_capacity' => $item->room ? $item->room->capacity : null,
                    'total_quantity' => (int) $item->total_quantity,
                    'total_bookings' => (int) $item->total_bookings,
                    'total_revenue' => (float) $item->total_revenue,
                    'avg_selling_price' => (float) $item->avg_selling_price,
                    'avg_quantity_per_booking' => round($item->total_quantity / $item->total_bookings, 2)
                ];
            });

            // Get summary statistics
            $totalQuantity = $formattedRooms->sum('total_quantity');
            $totalBookings = $formattedRooms->sum('total_bookings');
            $totalRevenue = $formattedRooms->sum('total_revenue');

            return response()->json([
                'status' => 1,
                'message' => 'Most selling rooms data retrieved successfully',
                'data' => [
                    'year' => $year,
                    'product_id' => $productId,
                    'product_type' => $productType,
                    'most_selling_rooms' => $formattedRooms,
                    'summary' => [
                        'total_rooms_listed' => $formattedRooms->count(),
                        'total_quantity_sold' => $totalQuantity,
                        'total_bookings' => $totalBookings,
                        'total_revenue' => $totalRevenue,
                        'avg_revenue_per_booking' => $totalBookings > 0 ? round($totalRevenue / $totalBookings, 2) : 0
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving most selling rooms data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
