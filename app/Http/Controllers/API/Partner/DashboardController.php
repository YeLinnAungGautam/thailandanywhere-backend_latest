<?php

namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Models\BookingItem;
use App\Models\CashImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get monthly sales graph data for a specific product from CashImage
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
            // Get monthly sales data from CashImage -> BookingItemGroup -> BookingItems
            $monthlySales = CashImage::select(
                DB::raw('MONTH(cash_images.date) as month'),
                DB::raw('SUM(
                    CASE
                        WHEN booking_items.checkin_date IS NOT NULL AND booking_items.checkout_date IS NOT NULL
                        THEN booking_items.quantity * DATEDIFF(booking_items.checkout_date, booking_items.checkin_date)
                        ELSE booking_items.quantity
                    END
                ) as total_quantity'),
                DB::raw('COUNT(booking_items.id) as total_items'),
                DB::raw('SUM(booking_items.total_cost_price) as total_income')
            )
            ->join('booking_item_groups', function($join) {
                $join->on('cash_images.relatable_id', '=', 'booking_item_groups.id')
                     ->where('cash_images.relatable_type', '=', 'App\Models\BookingItemGroup');
            })
            ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
            ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
            ->where('booking_items.product_id', $productId)
            ->where('booking_items.product_type', $productType)
            ->where('bookings.payment_status', 'fully_paid')
            ->whereYear('cash_images.date', $year)
            ->whereNull('booking_items.deleted_at')
            ->groupBy(DB::raw('MONTH(cash_images.date)'))
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

            // Get total unique bookings count through CashImage
            $totalUniqueBookings = CashImage::join('booking_item_groups', function($join) {
                    $join->on('cash_images.relatable_id', '=', 'booking_item_groups.id')
                         ->where('cash_images.relatable_type', '=', 'App\Models\BookingItemGroup');
                })
                ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
                ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
                ->where('booking_items.product_id', $productId)
                ->where('booking_items.product_type', $productType)
                ->where('bookings.payment_status', 'fully_paid')
                ->whereYear('cash_images.date', $year)
                ->whereNull('booking_items.deleted_at')
                ->distinct('bookings.id')
                ->count();

            // Get today's booking count through CashImage
            // Get today's BookingItemGroup count through CashImage
            $todayBookingItemGroupCount = CashImage::join('booking_item_groups', function($join) {
                $join->on('cash_images.relatable_id', '=', 'booking_item_groups.id')
                     ->where('cash_images.relatable_type', '=', 'App\Models\BookingItemGroup');
            })
            ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
            ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
            ->where('booking_items.product_id', $productId)
            ->where('booking_items.product_type', $productType)
            ->where('bookings.payment_status', 'fully_paid')
            ->whereYear('cash_images.date', $year)
            ->whereNull('booking_items.deleted_at')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('booking_items as bi')
                      ->whereColumn('bi.group_id', 'booking_item_groups.id')
                      ->whereNull('bi.deleted_at')
                      ->whereDate('bi.service_date', Carbon::today())
                      ->having(DB::raw('MIN(bi.service_date)'), '=', Carbon::today())
                      ->groupBy('bi.group_id');
            })
            ->distinct('booking_item_groups.id')
            ->count();

            return response()->json([
                'status' => 1,
                'message' => 'Monthly sales data retrieved successfully from CashImage',
                'data' => [
                    'year' => $year,
                    'product_id' => $productId,
                    'product_type' => $productType,
                    'monthly_sales' => $monthlyData,
                    'total_year_quantity' => array_sum(array_column($monthlyData, 'total_quantity')),
                    'total_year_items' => array_sum(array_column($monthlyData, 'total_items')),
                    'total_year_income' => array_sum(array_column($monthlyData, 'total_income')),
                    'total_unique_bookings' => $totalUniqueBookings,
                    'today_booking_count' => $todayBookingItemGroupCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving monthly sales data from CashImage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alternative method using Eloquent relationships (more readable but potentially slower)
     */
    public function getMonthlySalesGraphEloquent(Request $request)
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
            // Get CashImages with BookingItemGroup relatable type for the specific year
            $cashImages = CashImage::where('relatable_type', 'App\Models\BookingItemGroup')
                ->whereYear('date', $year)
                ->whereNull('deleted_at')
                ->with([
                    'relatable.bookingItems' => function($query) use ($productId, $productType) {
                        $query->where('product_id', $productId)
                              ->where('product_type', $productType)
                              ->whereNull('deleted_at');
                    },
                    'relatable.booking' => function($query) {
                        $query->where('payment_status', 'fully_paid');
                    }
                ])
                ->get();

            // Filter out cash images that don't have matching booking items or fully_paid bookings
            $validCashImages = $cashImages->filter(function($cashImage) {
                return $cashImage->relatable &&
                       $cashImage->relatable->booking &&
                       $cashImage->relatable->booking->payment_status === 'fully_paid' &&
                       $cashImage->relatable->bookingItems->count() > 0;
            });

            // Initialize monthly data
            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthlyData[$month] = [
                    'month' => $month,
                    'month_name' => Carbon::create()->month($month)->format('M'),
                    'total_quantity' => 0,
                    'total_items' => 0,
                    'total_income' => 0
                ];
            }

            $uniqueBookings = collect();
            $todayBookings = collect();

            // Process each cash image
            foreach ($validCashImages as $cashImage) {
                $month = $cashImage->date->month;

                foreach ($cashImage->relatable->bookingItems as $bookingItem) {
                    // Calculate quantity (handle checkin/checkout dates)
                    $quantity = $bookingItem->quantity;
                    if ($bookingItem->checkin_date && $bookingItem->checkout_date) {
                        $days = Carbon::parse($bookingItem->checkout_date)->diffInDays(Carbon::parse($bookingItem->checkin_date));
                        $quantity = $bookingItem->quantity * $days;
                    }

                    $monthlyData[$month]['total_quantity'] += $quantity;
                    $monthlyData[$month]['total_items']++;
                    $monthlyData[$month]['total_income'] += $bookingItem->total_cost_price ?? 0;
                }

                // Track unique bookings
                $booking = $cashImage->relatable->booking;
                $uniqueBookings->push($booking->id);

                // Check if today's booking
                if ($cashImage->date->isToday()) {
                    $todayBookings->push($booking->id);
                }
            }

            return response()->json([
                'status' => 1,
                'message' => 'Monthly sales data retrieved successfully from CashImage (Eloquent)',
                'data' => [
                    'year' => $year,
                    'product_id' => $productId,
                    'product_type' => $productType,
                    'monthly_sales' => array_values($monthlyData),
                    'total_year_quantity' => array_sum(array_column($monthlyData, 'total_quantity')),
                    'total_year_items' => array_sum(array_column($monthlyData, 'total_items')),
                    'total_year_income' => array_sum(array_column($monthlyData, 'total_income')),
                    'total_unique_bookings' => $uniqueBookings->unique()->count(),
                    'today_booking_count' => $todayBookings->unique()->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving monthly sales data from CashImage (Eloquent)',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
