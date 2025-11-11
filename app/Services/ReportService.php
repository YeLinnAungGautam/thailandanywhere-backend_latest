<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\EntranceTicket;
use App\Models\Hotel;
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
    ];

    /**
     * Get sales data grouped by agent
     */
    public static function getSalesByAgent(string $daterange)
    {
        [$start_date, $end_date] = self::parseDateRange($daterange);

        $bookings = Booking::query()
            ->join('admins', 'bookings.created_by', '=', 'admins.id')
            ->with('createdBy:id,name,target_amount')
            ->whereBetween('bookings.created_at', [$start_date, $end_date])
            ->selectRaw(
                'bookings.created_by,
                admins.target_amount as target_amount,
                GROUP_CONCAT(bookings.id) AS booking_ids,
                GROUP_CONCAT(CONCAT(DATE(bookings.created_at), "__", bookings.grand_total)) AS created_at_grand_total,
                SUM(bookings.grand_total) as total,
                COUNT(*) as total_booking'
            )
            ->groupBy('bookings.created_by', 'admins.target_amount')
            ->get();

        foreach ($bookings as $booking) {
            $booking->over_target_count = self::calculateOverTargetCount($booking);
        }

        return $bookings;
    }

    /**
     * Get product type sales based on fully paid cash images
     */
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
                $productData = self::getProductDataForDate($date, $productType);

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
    private static function getProductDataForDate(string $date, string $productType): ?array
    {
        $allBookingIdsFromCashImages = self::getAllFullyPaidBookingIdsFromCashImages();

        if (empty($allBookingIdsFromCashImages)) {
            return null;
        }

        $filteredBookingIds = self::getBookingIdsByProductAndDate(
            $allBookingIdsFromCashImages,
            $productType,
            $date
        );

        if (empty($filteredBookingIds)) {
            return null;
        }

        $expensePaidBookingIds = self::getBookingIdsWithExpensePayments();
        $financialData = self::calculateFinancialData($date, $filteredBookingIds, $expensePaidBookingIds, $productType);
        $itemStats = self::getBookingItemStats($date, $filteredBookingIds, $productType);
        $salesMetrics = self::getSalesMetrics($date, $filteredBookingIds, $productType);
        $remainExpense = self::calculateRemainingExpense($filteredBookingIds, $expensePaidBookingIds, $productType, $date);

        $totalProfit = $financialData['total_revenue'] - $financialData['total_cost'];
        $profitMargin = $financialData['total_revenue'] > 0
            ? ($totalProfit / $financialData['total_revenue'])
            : 0;

        return [
            'product_type' => $productType,
            'product_type_name' => self::getProductTypeName($productType),
            'booking_count' => $itemStats['booking_count'],
            'booking_item_count' => $itemStats['booking_item_count'],
            'total_quantity' => $itemStats['total_quantity'],
            'total_sales' => $financialData['total_revenue'],
            'total_expense' => $financialData['total_cost'],
            'total_profit' => round($totalProfit, 2),
            'profit_margin_percentage' => round($profitMargin * 100, 2),
            'booking_count_sale' => $salesMetrics['booking_count_sale'],
            'booking_item_count_sale' => $salesMetrics['booking_item_count_sale'],
            'remain_expense_total' => round($remainExpense, 2),
        ];
    }

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
     * Get ALL booking IDs from cash images (THB currency, fully paid only)
     */
    private static function getAllFullyPaidBookingIdsFromCashImages(): array
    {
        // Polymorphic relationship bookings
        $polymorphicIds = DB::table('cash_images')
            ->join('bookings', 'cash_images.relatable_id', '=', 'bookings.id')
            ->where('cash_images.relatable_type', Booking::class)
            ->where('cash_images.relatable_id', '>', 0)
            ->where('cash_images.data_verify', 1)
            ->where('cash_images.currency', 'THB')
            ->where('bookings.payment_status', 'fully_paid')
            ->pluck('cash_images.relatable_id')
            ->toArray();

        // Many-to-many relationship bookings
        $pivotIds = DB::table('cash_images')
            ->join('cash_image_bookings', 'cash_images.id', '=', 'cash_image_bookings.cash_image_id')
            ->join('bookings', 'cash_image_bookings.booking_id', '=', 'bookings.id')
            ->where('cash_images.relatable_type', Booking::class)
            ->where('cash_images.relatable_id', 0)
            ->where('cash_images.data_verify', 1)
            ->where('cash_images.currency', 'THB')
            ->where('bookings.payment_status', 'fully_paid')
            ->pluck('cash_image_bookings.booking_id')
            ->toArray();

        // BookingItemGroup relationship bookings
        $itemGroupIds = DB::table('cash_images')
            ->join('booking_item_groups', 'cash_images.relatable_id', '=', 'booking_item_groups.id')
            ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
            ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup')
            ->where('cash_images.relatable_id', '>', 0)
            ->where('cash_images.data_verify', 1)
            ->where('cash_images.currency', 'THB')
            ->where('bookings.payment_status', 'fully_paid')
            ->pluck('booking_item_groups.booking_id')
            ->toArray();

        return array_unique(array_merge($polymorphicIds, $pivotIds, $itemGroupIds));
    }

    /**
     * Get booking IDs filtered by product type and service date
     */
    private static function getBookingIdsByProductAndDate(
        array $bookingIds,
        string $productType,
        string $date
    ): array {
        return DB::table('booking_items')
            ->whereIn('booking_id', $bookingIds)
            ->where('product_type', $productType)
            ->whereDate('service_date', $date)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('booking_id')
            ->toArray();
    }

    /**
     * Get booking item statistics for a specific date (fully paid only)
     */
    private static function getBookingItemStats(string $date, array $bookingIds, string $productType): array
    {
        $stats = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->whereIn('booking_items.booking_id', $bookingIds)
            ->where('booking_items.product_type', $productType)
            ->whereDate('booking_items.service_date', $date)
            ->where('bookings.payment_status', 'fully_paid')
            ->where('booking_items.payment_status', 'fully_paid')
            ->whereNull('booking_items.deleted_at')
            ->selectRaw('
                COUNT(DISTINCT booking_items.booking_id) as booking_count,
                COUNT(booking_items.id) as booking_item_count,
                SUM(booking_items.quantity) as total_quantity
            ')
            ->first();

        return [
            'booking_count' => $stats->booking_count ?? 0,
            'booking_item_count' => $stats->booking_item_count ?? 0,
            'total_quantity' => $stats->total_quantity ?? 0,
        ];
    }

    /**
     * Get sales metrics (fully paid only) - FIXED: Properly qualify column names
     */
    private static function getSalesMetrics(string $date, array $bookingIds, string $productType): array
    {
        $salesMetrics = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->whereIn('booking_items.booking_id', $bookingIds)
            ->where('booking_items.product_type', $productType)
            ->whereDate('booking_items.service_date', $date)
            ->where('bookings.payment_status', 'fully_paid')
            ->where('booking_items.payment_status', 'fully_paid')
            ->whereNull('booking_items.deleted_at')
            ->selectRaw('
                COUNT(DISTINCT booking_items.booking_id) as booking_count_sale,
                COUNT(booking_items.id) as booking_item_count_sale
            ')
            ->first();

        return [
            'booking_count_sale' => $salesMetrics->booking_count_sale ?? 0,
            'booking_item_count_sale' => $salesMetrics->booking_item_count_sale ?? 0,
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
     * Calculate remaining expense for unpaid bookings
     */
    private static function calculateRemainingExpense(
        array $bookingIds,
        array $expensePaidBookingIds,
        string $productType,
        string $date
    ): float {
        $unpaidExpenseBookingIds = array_diff($bookingIds, $expensePaidBookingIds);

        if (empty($unpaidExpenseBookingIds)) {
            return 0;
        }

        $remainExpense = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->whereIn('booking_items.booking_id', $unpaidExpenseBookingIds)
            ->where('booking_items.product_type', $productType)
            ->whereDate('booking_items.service_date', $date)
            ->whereNull('booking_items.deleted_at')
            ->where('bookings.payment_status', 'fully_paid')
            ->where('booking_items.payment_status', '!=', 'fully_paid')
            ->sum(DB::raw('CAST(booking_items.total_cost_price AS DECIMAL(10,2))'));

        return $remainExpense ?? 0;
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
}
