<?php

namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Models\BookingItem;
use App\Models\BookingItemGroup;
use App\Models\CashImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get monthly sales graph data for a specific product
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
            // Get monthly booking data (quantity and items count)
            $monthlyBookingData = $this->getMonthlyBookingData($year, $productId, $productType);

            // Get monthly income data from CashImage
            $monthlyIncomeData = $this->getMonthlyIncomeData($year, $productId, $productType);

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

            // Fill in booking data (quantity and items)
            foreach ($monthlyBookingData as $booking) {
                $monthIndex = $booking->month - 1;
                $monthlyData[$monthIndex]['total_quantity'] = (int) $booking->total_quantity;
                $monthlyData[$monthIndex]['total_items'] = (int) $booking->total_items;
            }

            // Fill in income data from CashImage
            foreach ($monthlyIncomeData as $income) {
                $monthIndex = $income->month - 1;
                $monthlyData[$monthIndex]['total_income'] = (float) $income->total_income;
            }

            // Get additional statistics
            $totalUniqueBookings = $this->getTotalUniqueBookings($year, $productId, $productType);
            $todayBookingItemGroupCount = $this->getTodayBookingItemGroupCount($year, $productId, $productType);

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
                    'today_booking_count' => $todayBookingItemGroupCount
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
     * Get monthly booking data - Quantity from BookingItem, Count from BookingItemGroup
     */
    private function getMonthlyBookingData($year, $productId, $productType)
    {
        // Get quantity from BookingItems (only non-extra rooms)
        $quantityData = DB::table('booking_items')
            ->select(
                DB::raw('MONTH(service_date) as month'),
                DB::raw('SUM(
                    CASE
                        WHEN checkin_date IS NOT NULL AND checkout_date IS NOT NULL
                        THEN quantity * DATEDIFF(checkout_date, checkin_date)
                        ELSE quantity
                    END
                ) as total_quantity')
            )
            ->join('booking_item_groups', 'booking_items.group_id', '=', 'booking_item_groups.id')
            ->leftJoin('rooms', 'booking_items.room_id', '=', 'rooms.id')
            ->where('booking_items.product_id', $productId)
            ->where('booking_items.product_type', $productType)
            ->whereYear('booking_items.service_date', $year)
            ->whereNull('booking_items.deleted_at')
            ->where(function($query) {
                $query->whereNull('rooms.is_extra')
                      ->orWhere('rooms.is_extra', '!=', 1);
            })
            ->whereExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('cash_images')
                      ->whereColumn('cash_images.relatable_id', 'booking_item_groups.id')
                      ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup');
            })
            ->groupBy(DB::raw('MONTH(service_date)'))
            ->get()
            ->keyBy('month');

        // Get count from BookingItemGroups
        $countData = DB::table('booking_item_groups')
            ->select(
                DB::raw('MONTH(booking_items.service_date) as month'),
                DB::raw('COUNT(DISTINCT booking_item_groups.id) as total_items')
            )
            ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
            ->where('booking_items.product_id', $productId)
            ->where('booking_items.product_type', $productType)
            ->whereYear('booking_items.service_date', $year)
            ->whereNull('booking_items.deleted_at')
            ->whereExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('cash_images')
                      ->whereColumn('cash_images.relatable_id', 'booking_item_groups.id')
                      ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup');
            })
            ->groupBy(DB::raw('MONTH(booking_items.service_date)'))
            ->get()
            ->keyBy('month');

        // Combine results
        $result = collect();
        for ($month = 1; $month <= 12; $month++) {
            $quantity = isset($quantityData[$month]) ? (int) $quantityData[$month]->total_quantity : 0;
            $items = isset($countData[$month]) ? (int) $countData[$month]->total_items : 0;

            if ($quantity > 0 || $items > 0) {
                $result->push((object) [
                    'month' => $month,
                    'total_quantity' => $quantity,
                    'total_items' => $items
                ]);
            }
        }

        return $result;
    }

    /**
     * Get monthly income data from CashImage by date - Simple approach
     */
    private function getMonthlyIncomeData($year, $productId, $productType)
    {
        return DB::table('cash_images')
            ->select(
                DB::raw('MONTH(cash_images.date) as month'),
                DB::raw('SUM(cash_images.amount) as total_income')
            )
            ->join('booking_item_groups', 'cash_images.relatable_id', '=', 'booking_item_groups.id')
            ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
            ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup')
            ->where('booking_items.product_id', $productId)
            ->where('booking_items.product_type', $productType)
            ->whereYear('cash_images.date', $year)
            ->whereNull('booking_items.deleted_at')
            ->groupBy(DB::raw('MONTH(cash_images.date)'))
            ->orderBy('month')
            ->get();
    }

    /**
     * Get total unique bookings for the year
     */
    private function getTotalUniqueBookings($year, $productId, $productType)
    {
        return DB::table('booking_item_groups')
            ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
            ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
            ->where('booking_items.product_id', $productId)
            ->where('booking_items.product_type', $productType)
            ->whereYear('booking_items.service_date', $year)
            ->whereNull('booking_items.deleted_at')
            ->whereExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('cash_images')
                      ->whereColumn('cash_images.relatable_id', 'booking_item_groups.id')
                      ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup');
            })
            ->distinct('bookings.id')
            ->count();
    }

    /**
     * Get today's booking item group count
     */
    private function getTodayBookingItemGroupCount($year, $productId, $productType)
    {
        return DB::table('booking_item_groups')
            ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
            ->where('booking_items.product_id', $productId)
            ->where('booking_items.product_type', $productType)
            ->whereYear('booking_items.service_date', $year)
            ->whereDate('booking_items.service_date', Carbon::today())
            ->whereNull('booking_items.deleted_at')
            ->whereExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('cash_images')
                      ->whereColumn('cash_images.relatable_id', 'booking_item_groups.id')
                      ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup');
            })
            ->distinct('booking_item_groups.id')
            ->count();
    }

    /**
     * Get dashboard summary with filters
     */
    public function getDashboardSummary(Request $request)
    {
        $request->validate([
            'product_id' => 'nullable|integer',
            'product_type' => 'nullable|string',
            'date_range' => 'nullable|string',
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1)
        ]);

        try {
            $filters = [
                'product_id' => $request->product_id,
                'product_type' => $request->product_type,
                'date_range' => $request->date_range,
                'year' => $request->year ?? date('Y')
            ];

            // Get statistics
            $bookingGroupStats = $this->getBookingGroupStatistics($filters);
            $quantityStats = $this->getQuantityStatistics($filters);
            $incomeStats = $this->getIncomeStatistics($filters);

            return response()->json([
                'status' => 1,
                'message' => 'Dashboard summary retrieved successfully',
                'data' => array_merge($bookingGroupStats, $quantityStats, $incomeStats)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving dashboard summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get booking group statistics (for counting)
     */
    private function getBookingGroupStatistics($filters)
    {
        $query = DB::table('booking_item_groups')
            ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
            ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
            ->whereExists(function($q) {
                $q->select(DB::raw(1))
                  ->from('cash_images')
                  ->whereColumn('cash_images.relatable_id', 'booking_item_groups.id')
                  ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup');
            })
            ->whereNull('booking_items.deleted_at');

        $this->applyFilters($query, $filters);

        $totalGroups = $query->distinct('booking_item_groups.id')->count();
        $uniqueBookings = $query->distinct('bookings.id')->count();

        return [
            'total_booking_groups' => $totalGroups,
            'total_unique_bookings' => $uniqueBookings
        ];
    }

    /**
     * Get quantity statistics (from BookingItem, exclude extra rooms)
     */
    private function getQuantityStatistics($filters)
    {
        $query = DB::table('booking_items')
            ->join('booking_item_groups', 'booking_items.group_id', '=', 'booking_item_groups.id')
            ->leftJoin('rooms', 'booking_items.room_id', '=', 'rooms.id')
            ->whereExists(function($q) {
                $q->select(DB::raw(1))
                  ->from('cash_images')
                  ->whereColumn('cash_images.relatable_id', 'booking_item_groups.id')
                  ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup');
            })
            ->where(function($query) {
                $query->whereNull('rooms.is_extra')
                      ->orWhere('rooms.is_extra', '!=', 1);
            })
            ->whereNull('booking_items.deleted_at');

        $this->applyFilters($query, $filters);

        $items = $query->get(['booking_items.quantity', 'booking_items.checkin_date', 'booking_items.checkout_date']);

        $totalQuantity = 0;
        foreach ($items as $item) {
            if ($item->checkin_date && $item->checkout_date) {
                $days = Carbon::parse($item->checkout_date)->diffInDays(Carbon::parse($item->checkin_date));
                $totalQuantity += $item->quantity * $days;
            } else {
                $totalQuantity += $item->quantity;
            }
        }

        return [
            'total_quantity' => $totalQuantity,
            'total_items' => $items->count()
        ];
    }

    /**
     * Get income statistics (from CashImage by date - simple)
     */
    private function getIncomeStatistics($filters)
    {
        $query = DB::table('cash_images')
            ->join('booking_item_groups', 'cash_images.relatable_id', '=', 'booking_item_groups.id')
            ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
            ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup')
            ->whereNull('booking_items.deleted_at');

        $this->applyFilters($query, $filters, 'cash_images.date');

        $totalIncome = $query->sum('cash_images.amount');
        $totalCashImages = $query->count();
        $avgAmount = $totalCashImages > 0 ? $query->avg('cash_images.amount') : 0;

        return [
            'total_income' => $totalIncome,
            'total_cash_images' => $totalCashImages,
            'average_transaction_amount' => $avgAmount
        ];
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, $filters, $dateColumn = 'booking_items.service_date')
    {
        // Product filters
        if ($filters['product_id']) {
            $query->where('booking_items.product_id', $filters['product_id']);
        }

        if ($filters['product_type']) {
            $query->where('booking_items.product_type', $filters['product_type']);
        }

        // Date range filter
        if ($filters['date_range']) {
            $dates = array_map('trim', explode(',', $filters['date_range']));
            if (count($dates) === 2) {
                $query->whereBetween($dateColumn, $dates);
            } elseif (count($dates) === 1) {
                $query->whereDate($dateColumn, $dates[0]);
            }
        }

        // Year filter
        if ($filters['year']) {
            $query->whereYear($dateColumn, $filters['year']);
        }
    }
}
