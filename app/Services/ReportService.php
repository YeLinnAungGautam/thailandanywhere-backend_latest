<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\EntranceTicket;
use App\Models\Hotel;
use App\Models\InclusiveProduct;
use App\Models\PrivateVanTour;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    // Product type constants for better maintainability
    private const PRODUCT_TYPE_MAP = [
        Hotel::class => 'Hotel Service',
        EntranceTicket::class => 'Entrance Ticket',
        PrivateVanTour::class => 'Private Van Tour',
        Airline::class => 'Airline',
        'App\Models\GroupTour' => 'Group Tour',

    ];

    private const PRODUCT_TYPES_FOR_ANALYSIS = [
        Hotel::class,
        EntranceTicket::class,
        PrivateVanTour::class,
        InclusiveProduct::class
    ];

    public static function getSalesByAgent(string $daterange)
    {
        [$start_date, $end_date] = self::parseDateRange($daterange);

        // Get airline totals per booking
        $airlineTotals = DB::table('booking_items')
            ->where('product_type', 'App\\Models\\Airline')
            ->selectRaw('booking_id, SUM(amount) as airline_amount')
            ->groupBy('booking_id')
            ->pluck('airline_amount', 'booking_id');

        $bookings = Booking::query()
            ->join('admins', 'bookings.created_by', '=', 'admins.id')
            ->with('createdBy:id,name,target_amount')
            ->whereBetween('bookings.created_at', [$start_date, $end_date])
            ->selectRaw(
                'bookings.created_by,
                admins.target_amount as target_amount,
                GROUP_CONCAT(bookings.id) AS booking_ids,
                GROUP_CONCAT(CONCAT(bookings.id, "||", DATE(bookings.created_at), "__", bookings.grand_total)) AS created_at_grand_total,
                SUM(bookings.grand_total) as total_with_airline,
                COUNT(*) as total_booking'
            )
            ->groupBy('bookings.created_by', 'admins.target_amount')
            ->get();

        foreach ($bookings as $booking) {
            $bookingIds = !empty($booking->booking_ids) ? explode(',', $booking->booking_ids) : [];

            // Calculate total airline amount for this agent
            $airlineTotal = 0;
            foreach ($bookingIds as $bookingId) {
                $airlineTotal += $airlineTotals[$bookingId] ?? 0;
            }

            // Update date-wise grand totals to separate airline amounts
            $dateGrandTotals = !empty($booking->created_at_grand_total)
                ? explode(',', $booking->created_at_grand_total)
                : [];

            $updatedDateGrandTotalsWithAirline = [];
            $updatedDateGrandTotalsWithoutAirline = [];

            foreach ($dateGrandTotals as $item) {
                // Check if the item contains the delimiter
                if (strpos($item, '||') === false) {
                    // Skip malformed items
                    continue;
                }

                $parts = explode('||', $item, 2); // Limit to 2 parts
                if (count($parts) !== 2) {
                    continue;
                }

                [$bookingId, $dateAndTotal] = $parts;

                // Check if dateAndTotal contains the delimiter
                if (strpos($dateAndTotal, '__') === false) {
                    continue;
                }

                $dateParts = explode('__', $dateAndTotal, 2);
                if (count($dateParts) !== 2) {
                    continue;
                }

                [$date, $grandTotal] = $dateParts;

                // Get airline amount for this specific booking
                $bookingAirlineAmount = $airlineTotals[$bookingId] ?? 0;
                $adjustedGrandTotal = $grandTotal - $bookingAirlineAmount;

                // Store both versions
                $updatedDateGrandTotalsWithAirline[] = $date . '__' . $grandTotal;
                $updatedDateGrandTotalsWithoutAirline[] = $date . '__' . $adjustedGrandTotal;
            }

            // Set all values
            $booking->airline_total = $airlineTotal;
            $booking->total_with_airline = $booking->total_with_airline; // Total including airline
            $booking->total_without_airline = $booking->total_with_airline - $airlineTotal; // Total excluding airline
            $booking->created_at_grand_total_with_airline = implode(',', $updatedDateGrandTotalsWithAirline);
            $booking->created_at_grand_total_without_airline = implode(',', $updatedDateGrandTotalsWithoutAirline);

            // Default to total with airline for backward compatibility
            $booking->total = $booking->total_with_airline;
            $booking->created_at_grand_total = $booking->created_at_grand_total_with_airline;

            $booking->over_target_count = self::calculateOverTargetCount($booking);
        }

        return $bookings;
    }
    public static function getProductTypeSales(string $daterange)
    {
        [$start_date, $end_date] = self::parseDateRange($daterange);
        $allDates = self::getDateRange($start_date, $end_date);
        $results = [];

        foreach ($allDates as $date) {
            $dateData = [
                'date' => $date,
                'product_types' => []
            ];

            foreach (self::PRODUCT_TYPES_FOR_ANALYSIS as $productType) {
                $productData = self::getProductDataForDateNew($date, $productType);

                if (!empty($productData)) {
                    $dateData['product_types'][] = $productData;
                }
            }

            if (!empty($dateData['product_types'])) {
                $results[] = $dateData;
            }
        }

        return collect($results);
    }

    private static function getProductDataForDateNew(string $date, string $productType): ?array
    {
        // Get all booking items for this product type where booking_date matches
        $bookingItems = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->where('booking_items.product_type', $productType)
            ->whereDate('bookings.booking_date', $date)
            ->whereNull('booking_items.deleted_at')
            ->whereNotNull('booking_items.amount')
            ->where('booking_items.amount', '>', 0)
            ->when($productType !== InclusiveProduct::class, function($query) {
                $query->where(function($q) {
                    $q->whereNotNull('booking_items.total_cost_price')
                      ->where('booking_items.total_cost_price', '>', 0);
                });
            })
            ->select(
                'booking_items.booking_id',
                'booking_items.quantity',
                'booking_items.amount',
                'booking_items.total_cost_price',
                'booking_items.payment_status as item_payment_status',
                'bookings.payment_status as booking_payment_status'
            )
            ->get();

        if ($bookingItems->isEmpty()) {
            return null;
        }

        $totalRevenue = 0;
        $totalCost = 0;
        $totalProfit = 0;
        $totalQuantity = 0;
        $bookingIds = [];
        $fullyPaidBookingIds = [];
        $bookingItemCount = 0;
        $fullyPaidItemCount = 0;

        // Total expense calculation: sum cost where item payment_status is fully_paid
        $totalExpense = 0;

        foreach ($bookingItems as $item) {
            $itemAmount = (float)$item->amount;
            $itemCost = (float)$item->total_cost_price;

            // Total calculations (all items)
            $totalRevenue += $itemAmount;
            $totalCost += $itemCost;
            $totalProfit += ($itemAmount - $itemCost); // Profit per item
            $totalQuantity += $item->quantity;
            $bookingIds[] = $item->booking_id;
            $bookingItemCount++;

            // Expense: only if item payment_status is fully_paid
            if ($item->item_payment_status === 'fully_paid') {
                $totalExpense += $itemCost;
            }

            // Sales metrics: booking and item both fully_paid
            if ($item->booking_payment_status === 'fully_paid' &&
                $item->item_payment_status === 'fully_paid') {
                $fullyPaidBookingIds[] = $item->booking_id;
                $fullyPaidItemCount++;
            }
        }

        $bookingIds = array_unique($bookingIds);
        $fullyPaidBookingIds = array_unique($fullyPaidBookingIds);

        $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) : 0;

        // Remaining expense: items where booking is fully_paid but item is not fully_paid
        $remainExpense = $bookingItems
            ->filter(fn($item) =>
                $item->booking_payment_status === 'fully_paid' &&
                $item->item_payment_status !== 'fully_paid'
            )
            ->sum(fn($item) => (float)$item->total_cost_price);

        return [
            'product_type' => $productType,
            'product_type_name' => self::getProductTypeName($productType),
            'booking_count' => count($bookingIds),
            'booking_item_count' => $bookingItemCount,
            'total_quantity' => $totalQuantity,
            'total_sales' => round($totalRevenue, 2),
            'total_expense' => round($totalExpense, 2),
            'total_profit' => round($totalProfit, 2),
            'profit_margin_percentage' => round($profitMargin * 100, 2),
            'booking_count_sale' => count($fullyPaidBookingIds),
            'booking_item_count_sale' => $fullyPaidItemCount,
            'remain_expense_total' => round($remainExpense, 2),
        ];
    }



    /**
     * Calculate remaining expense (revenue paid but expense not paid)
     */
    private static function calculateRemainingExpenseNew(string $productType, string $date): float
    {
        // Get booking IDs that have expense payments
        $expensePaidBookingIds = self::getBookingIdsWithExpensePayments();

        // FIXED: Use booking_date instead of service_date
        // Get items where booking is fully paid but expense is not paid
        $remainExpense = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->where('booking_items.product_type', $productType)
            ->whereDate('bookings.booking_date', $date)  // FIXED: Use booking_date
            ->whereNotNull('booking_items.amount')
            ->whereNotNull('booking_items.total_cost_price')
            ->where('booking_items.amount', '>', 0)
            ->whereNull('booking_items.deleted_at')
            ->where('bookings.payment_status', 'fully_paid')
            ->where('booking_items.payment_status', 'fully_paid')
            ->whereNotIn('booking_items.booking_id', $expensePaidBookingIds)
            ->sum(DB::raw('CAST(booking_items.total_cost_price AS DECIMAL(10,2))'));

        return $remainExpense ?? 0;
    }

    /**
     * Get unpaid bookings within date range
     */
    public static function getUnpaidBooking(
        string $daterange,
        ?string $agent_id = null,
        ?string $service_daterange = null
    ) {
        [$start_date, $end_date] = self::parseDateRange($daterange);
        $today_date = Carbon::now()->format('Y-m-d');

        return Booking::query()
            ->with('createdBy:id,name')
            ->when($agent_id, fn($query) => $query->where('created_by', $agent_id))
            ->when($service_daterange, function ($query) use ($service_daterange) {
                [$service_start, $service_end] = self::parseDateRange($service_daterange);

                $query->whereHas('items', function ($q) use ($service_start, $service_end) {
                    $q->whereBetween('service_date', [$service_start, $service_end]);
                });
            })
            ->whereBetween('created_at', [$start_date, $end_date])
            ->where('balance_due_date', '<', $today_date)
            ->whereIn('payment_status', ['partially_paid', 'not_paid'])
            ->groupBy('created_by')
            ->selectRaw(
                'created_by,
                GROUP_CONCAT(id) AS booking_ids,
                SUM(balance_due) as total_balance,
                COUNT(*) as total_booking'
            )
            ->get();
    }

    /**
     * Get count statistics for various booking types
     */
    public static function getCountReport(string $daterange): array
    {
        [$start_date, $end_date] = self::parseDateRange($daterange);

        return [
            'booking_count' => Booking::whereBetween('created_at', [$start_date, $end_date])->count(),
            'van_tour_sale_count' => self::getProductTypeCount(PrivateVanTour::class, $start_date, $end_date),
            'attraction_sale_count' => self::getProductTypeCount(EntranceTicket::class, $start_date, $end_date),
            'hotel_sale_count' => self::getProductTypeCount(Hotel::class, $start_date, $end_date),
            'air_ticket_sale_count' => self::getProductTypeCount(Airline::class, $start_date, $end_date),
        ];
    }

    /**
     * Get top selling products
     */
    public static function getTopSellingProduct(
        string $daterange,
        ?string $product_type = null,
        string|int|null $limit = null
    ) {
        [$start_date, $end_date] = self::parseDateRange($daterange);

        return BookingItem::query()
            ->with('product:id,name')
            ->when($product_type, fn($query) => $query->where('product_type', $product_type))
            ->whereBetween('created_at', [$start_date, $end_date])
            ->groupBy(
                'product_id',
                'product_type',
                'variation_id',
                'car_id',
                'room_id',
                'ticket_id'
            )
            ->select(
                'product_id',
                'product_type',
                'variation_id',
                'car_id',
                'room_id',
                'ticket_id',
                DB::raw('GROUP_CONCAT(id) AS reservation_ids'),
                DB::raw('GROUP_CONCAT(selling_price) AS selling_prices'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->orderByDesc('total_quantity')
            ->paginate($limit ?? 5);
    }

    // ==================== Private Helper Methods ====================

    /**
     * Get product data for specific date and type
     */


    /**
     * Calculate financial data from booking items (fully paid only)
     */
    private static function calculateFinancialData(
        string $date,
        array $bookingIds,
        array $expensePaidBookingIds,
        string $productType
    ): array {
        // Revenue: Only from FULLY PAID bookings and booking items
        $revenueData = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->whereIn('booking_items.booking_id', $bookingIds)
            ->where('booking_items.product_type', $productType)
            ->whereDate('booking_items.service_date', $date)
            ->where('bookings.payment_status', 'fully_paid')
            ->where('booking_items.payment_status', 'fully_paid')
            ->whereNull('booking_items.deleted_at')
            ->selectRaw('SUM(CAST(booking_items.amount AS DECIMAL(10,2))) as total_revenue')
            ->first();

        // Cost: Only from FULLY PAID bookings with expense payments
        $costBookingIds = array_intersect($bookingIds, $expensePaidBookingIds);
        $costData = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->whereIn('booking_items.booking_id', $costBookingIds)
            ->where('booking_items.product_type', $productType)
            ->whereDate('booking_items.service_date', $date)
            ->where('bookings.payment_status', 'fully_paid')
            ->where('booking_items.payment_status', 'fully_paid')
            ->whereNull('booking_items.deleted_at')
            ->selectRaw('SUM(CAST(booking_items.total_cost_price AS DECIMAL(10,2))) as total_cost')
            ->first();

        return [
            'total_revenue' => round($revenueData->total_revenue ?? 0, 2),
            'total_cost' => round($costData->total_cost ?? 0, 2),
        ];
    }

    /**
     * Get booking IDs that have expense payments
     */
    private static function getBookingIdsWithExpensePayments(): array
    {
        return DB::table('cash_images')
            ->join('booking_item_groups', 'cash_images.relatable_id', '=', 'booking_item_groups.id')
            ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup')
            ->where('cash_images.relatable_id', '>', 0)
            ->where('cash_images.data_verify', 1)
            ->where('cash_images.currency', 'THB')
            ->distinct()
            ->pluck('booking_item_groups.booking_id')
            ->toArray();
    }


    /**
     * Calculate how many days an agent exceeded their target
     */
    private static function calculateOverTargetCount($booking): int
    {
        $created_grand_total = explode(',', $booking->created_at_grand_total);

        $grouped = collect($created_grand_total)
            ->groupBy(fn($item) => explode('__', $item)[0])
            ->toArray();

        $filteredDates = [];

        foreach ($grouped as $date => $entries) {
            $total = collect($entries)
                ->map(fn($entry) => (float)(explode('__', $entry)[1] ?? 0))
                ->sum();

            if ($total >= $booking->target_amount) {
                $filteredDates[$date] = count($entries);
            }
        }

        return array_sum($filteredDates);
    }

    /**
     * Parse date range string into start and end dates
     */
    private static function parseDateRange(string $daterange): array
    {
        $dates = explode(',', $daterange);

        return [
            Carbon::parse($dates[0])->format('Y-m-d'),
            Carbon::parse($dates[1])->format('Y-m-d'),
        ];
    }

    /**
     * Get all dates between start and end date
     */
    private static function getDateRange(string $start_date, string $end_date): array
    {
        $dates = [];
        $current = Carbon::parse($start_date);
        $end = Carbon::parse($end_date);

        while ($current->lte($end)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Get count of booking items for a product type
     */
    private static function getProductTypeCount(string $productType, string $start_date, string $end_date): int
    {
        return BookingItem::where('product_type', $productType)
            ->whereBetween('created_at', [$start_date, $end_date])
            ->count();
    }

    /**
     * Get product type display name
     */
    private static function getProductTypeName(string $productType): string
    {
        return self::PRODUCT_TYPE_MAP[$productType] ?? 'Unknown';
    }

    public static function getDashboardSummary(string $daterange, ?string $admin_id = null)
    {
        // Parse the daterange (format: YYYY-MM)
        $month = substr($daterange, 0, 2);
        $year = substr($daterange, 3, 4);

        // Build the base query for bookings
        $bookingsQuery = Booking::whereYear('booking_date', $year)
            ->whereMonth('booking_date', $month);

        // Filter by admin if provided
        if ($admin_id) {
            $bookingsQuery->where('created_by', $admin_id);
        }

        // Get all bookings for the period
        $bookings = $bookingsQuery->get();
        $totalBookings = $bookings->count();

        // Calculate average grand total
        $averageGrandTotal = $totalBookings > 0
            ? $bookings->avg('grand_total')
            : 0;

        // Get all booking items with their product types
        $bookingIds = $bookings->pluck('id');

        $bookingItems = BookingItem::whereIn('booking_id', $bookingIds)
            ->get();

        $totalItems = $bookingItems->count();

        // Group by product_type and count
        $productTypeCounts = $bookingItems->groupBy('product_type')
            ->map(function ($items, $productType) use ($totalItems) {
                $count = $items->count();
                $percentage = $totalItems > 0
                    ? round(($count / $totalItems) * 100, 2)
                    : 0;

                // Get human-readable product type name
                $productTypeName = $items->first()->acsr_product_type_name ?? 'Unknown';

                return [
                    'product_type' => $productType,
                    'product_type_name' => $productTypeName,
                    'count' => $count,
                    'percentage' => $percentage,
                ];
            })
            ->values(); // Reset array keys

        // Calculate grand total sum
        $totalGrandTotal = $bookings->sum('grand_total');

        return [
            'period' => $daterange,
            'total_bookings' => $totalBookings,
            'average_grand_total' => round($averageGrandTotal, 2),
            'total_grand_total' => round($totalGrandTotal, 2),
            'product_type_summary' => $productTypeCounts,
            'total_booking_items' => $totalItems,
        ];
    }
}
