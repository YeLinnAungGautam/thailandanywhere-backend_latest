<?php

namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Models\BookingItem;
use App\Models\CashImage;
use App\Models\BookingItemGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get monthly sales graph data for a specific product from BookingItemGroup
     * Uses ANY service date from booking items instead of just the first service date
     * This matches the logic used in ReservationController
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
            // Get monthly sales data based on service date of booking items
            // Group by month of service_date, not first service date
            $monthlySales = BookingItemGroup::select(
                DB::raw('MONTH(booking_items.service_date) as month'),
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
            ->join('cash_images', function($join) {
                $join->on('booking_item_groups.id', '=', 'cash_images.relatable_id')
                     ->where('cash_images.relatable_type', '=', 'App\Models\BookingItemGroup');
            })
            ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
            ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
            ->where('booking_items.product_id', $productId)
            ->where('booking_items.product_type', $productType)
            // Payment status determined by presence of cash images (already joined above)
            ->whereYear('booking_items.service_date', $year) // Changed: filter by ANY booking item service_date in year
            ->whereNull('booking_items.deleted_at')
            ->groupBy(DB::raw('MONTH(booking_items.service_date)'))
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

            // Get total unique bookings count - now consistent with ReservationController logic
            $totalUniqueBookings = BookingItemGroup::join('cash_images', function($join) {
                    $join->on('booking_item_groups.id', '=', 'cash_images.relatable_id')
                         ->where('cash_images.relatable_type', '=', 'App\Models\BookingItemGroup');
                })
                ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
                ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
                ->where('booking_items.product_id', $productId)
                ->where('booking_items.product_type', $productType)
                // Payment status determined by presence of cash images (already joined above)
                ->whereYear('booking_items.service_date', $year) // Changed: filter by ANY booking item service_date in year
                ->whereNull('booking_items.deleted_at')
                ->distinct('bookings.id')
                ->count();

            // Get today's BookingItemGroup count based on ANY service date being today
            $todayBookingItemGroupCount = BookingItemGroup::whereHas('cashImages')
                ->whereHas('bookingItems', function($query) use ($productId, $productType) {
                    $query->where('product_id', $productId)
                          ->where('product_type', $productType)
                          ->whereDate('service_date', Carbon::today()) // Changed: ANY booking item with service_date today
                          ->whereNull('deleted_at');
                })

                ->count();

            return response()->json([
                'status' => 1,
                'message' => 'Monthly sales data retrieved successfully from BookingItemGroup using service date (consistent with ReservationController)',
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
                'message' => 'Error retrieving monthly sales data from BookingItemGroup using service date',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eloquent-based method using BookingItemGroup relationships
     * Uses ANY service date from booking items instead of just the first service date
     * This matches the logic used in ReservationController
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
            // Get BookingItemGroups that have cash images and contain the specified product
            // Filter by ANY service date being in the specified year (consistent with ReservationController)
            $bookingItemGroups = BookingItemGroup::whereHas('cashImages')
                ->whereHas('bookingItems', function($query) use ($productId, $productType, $year) {
                    $query->where('product_id', $productId)
                          ->where('product_type', $productType)
                          ->whereYear('service_date', $year) // Changed: filter by ANY booking item service_date in year
                          ->whereNull('deleted_at');
                })
                // Payment status determined by presence of cash images (checked above with whereHas('cashImages'))
                ->with([
                    'cashImages',
                    'bookingItems' => function($query) use ($productId, $productType, $year) {
                        $query->where('product_id', $productId)
                              ->where('product_type', $productType)
                              ->whereYear('service_date', $year) // Also filter booking items by year
                              ->whereNull('deleted_at')
                              ->orderBy('service_date', 'asc');
                    },
                    'booking'
                ])
                ->get();

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
            $todayBookingGroups = collect();

            // Process each booking item group
            foreach ($bookingItemGroups as $group) {
                foreach ($group->bookingItems as $bookingItem) {
                    $serviceDate = Carbon::parse($bookingItem->service_date);
                    $month = $serviceDate->month;

                    // Calculate quantity (handle checkin/checkout dates)
                    $quantity = $bookingItem->quantity;
                    if ($bookingItem->checkin_date && $bookingItem->checkout_date) {
                        $days = Carbon::parse($bookingItem->checkout_date)->diffInDays(Carbon::parse($bookingItem->checkin_date));
                        $quantity = $bookingItem->quantity * $days;
                    }

                    $monthlyData[$month]['total_quantity'] += $quantity;
                    $monthlyData[$month]['total_items']++;
                    $monthlyData[$month]['total_income'] += $bookingItem->total_cost_price ?? 0;

                    // Check if this booking item's service date is today
                    if ($serviceDate->isToday()) {
                        $todayBookingGroups->push($group->id);
                    }
                }

                // Track unique bookings
                $uniqueBookings->push($group->booking->id);
            }

            return response()->json([
                'status' => 1,
                'message' => 'Monthly sales data retrieved successfully from BookingItemGroup using service date (Eloquent, consistent with ReservationController)',
                'data' => [
                    'year' => $year,
                    'product_id' => $productId,
                    'product_type' => $productType,
                    'monthly_sales' => array_values($monthlyData),
                    'total_year_quantity' => array_sum(array_column($monthlyData, 'total_quantity')),
                    'total_year_items' => array_sum(array_column($monthlyData, 'total_items')),
                    'total_year_income' => array_sum(array_column($monthlyData, 'total_income')),
                    'total_unique_bookings' => $uniqueBookings->unique()->count(),
                    'today_booking_count' => $todayBookingGroups->unique()->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving monthly sales data from BookingItemGroup using service date (Eloquent)',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary statistics for BookingItemGroups
     */
    public function getBookingItemGroupStats(Request $request)
    {
        $request->validate([
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
            'product_id' => 'nullable|integer',
            'product_type' => 'nullable|string'
        ]);

        try {
            $query = BookingItemGroup::query();

            // Apply filters if provided
            if ($request->year) {
                // Changed: Use consistent date filtering logic
                $query->whereHas('bookingItems', function($q) use ($request) {
                    $q->whereYear('service_date', $request->year)
                      ->whereNull('deleted_at');
                });
            }

            if ($request->product_id && $request->product_type) {
                $query->whereHas('bookingItems', function($q) use ($request) {
                    $q->where('product_id', $request->product_id)
                      ->where('product_type', $request->product_type)
                      ->whereNull('deleted_at');
                });
            }

            // Payment status determined by presence of cash images (checked above with whereHas('cashImages'))
            $stats = [
                'total_booking_item_groups' => $query->count(),
                'groups_with_cash_images' => $query->has('cashImages')->count(),
                'fully_paid_groups' => $query->has('cashImages')->count(), // Same as groups_with_cash_images since cash image = fully paid
                'groups_with_passports' => $query->has('passports')->count(),
                'groups_with_customer_documents' => $query->has('customerDocuments')->count(),
            ];

            return response()->json([
                'status' => 1,
                'message' => 'BookingItemGroup statistics retrieved successfully',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving BookingItemGroup statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
