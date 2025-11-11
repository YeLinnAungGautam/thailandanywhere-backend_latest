<?php

namespace App\Services;

use App\Models\CashImage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CashImageProfitService
{
    const VALID_INTERACT_BANK = [
        'personal', 'company', 'all', 'cash_at_office', 'to_money_changer', 'deposit_management', 'pay_to_driver'
    ];

    const VALID_CURRENCY = ['MMK', 'THB', 'USD'];

    const VALID_PRODUCT_TYPES = [
        'App\Models\Hotel',
        'App\Models\EntranceTicket',
        'App\Models\PrivateVanTour',
        'App\Models\Airline',
        'App\Models\GroupTour',
    ];

    const VALID_PAYMENT_STATUS = [
        'fully_paid', 'partially_paid', 'not_paid'
    ];

    /**
     * Generate profit report
     */
    public function generateProfitReport(Request $request)
    {
        try {
            $this->validateRequest($request);

            $filters = $this->extractFilters($request);

            // Get booking items data
            $bookingItemsList = $this->getBookingItemsList($filters);

            // Calculate total sales from cash images (Booking relatable_type)
            $totalSales = $this->calculateTotalSales($filters);

            // Calculate total expense from cash images (BookingItemGroup relatable_type)
            $totalExpense = $this->calculateTotalExpense($filters);

            // Calculate profit
            $totalProfit = $totalSales - $totalExpense;
            $profitMargin = $totalSales > 0 ? $totalProfit / $totalSales : 0;

            // Create summary
            $summary = [
                'total_sales' => round($totalSales, 2),
                'total_expense' => round($totalExpense, 2),
                'total_profit' => round($totalProfit, 2),
                'profit_margin_percentage' => round($profitMargin, 2),
                'total_booking_items' => count($bookingItemsList),
            ];

            return [
                'success' => true,
                'data' => [
                    'filters_applied' => $filters,
                    'booking_items' => $bookingItemsList,
                    'summary' => $summary,
                ],
                'message' => 'Profit report generated successfully'
            ];

        } catch (InvalidArgumentException $e) {
            Log::error('Profit Report Validation Error: ' . $e->getMessage());

            return [
                'success' => false,
                'data' => null,
                'message' => 'Validation Error: ' . $e->getMessage(),
                'error_type' => 'validation'
            ];
        } catch (Exception $e) {
            Log::error('Profit Report Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return [
                'success' => false,
                'data' => null,
                'message' => 'An error occurred while generating profit report. Error: ' . $e->getMessage(),
                'error_type' => 'system'
            ];
        }
    }

    /**
     * Validate request
     */
    private function validateRequest(Request $request)
    {
        $validator = Validator(
            $request->all(),
            [
                'month' => 'required|date_format:Y-m',
                'interact_bank' => 'required|in:' . implode(',', self::VALID_INTERACT_BANK),
                'product_type' => 'required|in:' . implode(',', self::VALID_PRODUCT_TYPES),
                'booking_payment_status' => 'nullable|in:' . implode(',', self::VALID_PAYMENT_STATUS),
                'booking_item_payment_status' => 'required|in:' . implode(',', self::VALID_PAYMENT_STATUS),
                'currency' => 'nullable|in:' . implode(',', self::VALID_CURRENCY),
            ]
        );

        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first());
        }
    }

    /**
     * Extract filters from request
     */
    private function extractFilters(Request $request)
    {
        $month = $request->input('month'); // Format: 2025-09

        return [
            'month' => $month,
            'year' => substr($month, 0, 4),
            'month_number' => substr($month, 5, 2),
            'interact_bank' => $request->input('interact_bank'),
            'product_type' => $request->input('product_type'),
            'booking_payment_status' => $request->input('booking_payment_status'),
            'booking_item_payment_status' => $request->input('booking_item_payment_status'),
            'currency' => $request->input('currency'),
        ];
    }

    /**
     * Get booking items list
     */
    private function getBookingItemsList($filters)
    {
        // Get all bookings related to filtered cash images
        $bookingIds = $this->getRelatedBookingIds($filters);

        if (empty($bookingIds)) {
            return [];
        }

        // Query booking items with filters
        $bookingItems = DB::table('booking_items')
            ->select([
                'booking_items.id',
                'booking_items.booking_id',
                'booking_items.product_id',
                'booking_items.product_type',
                'booking_items.quantity',
                'booking_items.selling_price',
                'booking_items.amount',
                'booking_items.total_cost_price',
                'booking_items.discount',
                'booking_items.output_vat',
                'booking_items.commission',
                'booking_items.payment_status',
                'booking_items.service_date',
                'booking_items.days',
                'booking_items.comment',
                'bookings.crm_id',
                'bookings.booking_date',
                'bookings.payment_status as booking_payment_status',
                'bookings.customer_id',
                'customers.name as customer_name',
                'customers.phone_number as customer_phone',
                'customers.email as customer_email',
            ])
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->leftJoin('customers', 'bookings.customer_id', '=', 'customers.id')
            ->whereIn('booking_items.booking_id', $bookingIds)
            ->where('booking_items.product_type', $filters['product_type'])
            ->where('booking_items.payment_status', $filters['booking_item_payment_status'])
            ->whereYear('booking_items.service_date', $filters['year'])
            ->whereMonth('booking_items.service_date', $filters['month_number']);

        // Apply booking payment status filter if provided
        if (!empty($filters['booking_payment_status'])) {
            $bookingItems->where('bookings.payment_status', $filters['booking_payment_status']);
        }

        $items = $bookingItems->get();

        // Get product names
        $result = [];
        foreach ($items as $item) {
            $productName = $this->getProductName($item->product_id, $item->product_type);

            $revenue = $item->amount ?? 0;
            $cost = $item->total_cost_price ?? 0;
            $profit = $revenue - $cost;
            $profitMargin = $revenue > 0 ? $profit / $revenue : 0 ;

            $result[] = [
                'booking_item_id' => $item->id,
                'booking_id' => $item->booking_id,
                'crm_id' => $item->crm_id,
                'booking_date' => $item->booking_date,
                'booking_payment_status' => $item->booking_payment_status,
                'customer' => [
                    'id' => $item->customer_id,
                    'name' => $item->customer_name,
                    'phone_number' => $item->customer_phone,
                    'email' => $item->customer_email,
                ],
                'product_id' => $item->product_id,
                'product_type' => $item->product_type,
                'product_name' => $productName,
                'service_date' => $item->service_date,
                'quantity' => $item->quantity,
                'days' => $item->days,
                'selling_price' => $item->selling_price,
                'amount' => $revenue,
                'total_cost_price' => $cost,
                'discount' => $item->discount ?? 0,
                'output_vat' => $item->output_vat ?? 0,
                'commission' => $item->commission ?? 0,
                'payment_status' => $item->payment_status,
                'profit' => round($profit, 2),
                'profit_margin_percentage' => round($profitMargin, 2),
                'comment' => $item->comment,
            ];
        }

        return $result;
    }

    /**
     * Get related booking IDs from cash images
     */
    private function getRelatedBookingIds($filters)
    {
        $bookingIds = [];

        // Query for Booking relatable_type (polymorphic)
        $polymorphicBookingIds = DB::table('cash_images')
            ->select('relatable_id')
            ->where('interact_bank', $filters['interact_bank'])
            ->where('relatable_type', 'App\Models\Booking')
            ->where('relatable_id', '>', 0)
            ->where('data_verify', 1)
            ->when($filters['currency'], function($q) use ($filters) {
                $q->where('currency', $filters['currency']);
            })
            ->pluck('relatable_id')
            ->toArray();

        // Query for many-to-many relationship
        $pivotBookingIds = DB::table('cash_images')
            ->join('cash_image_bookings', 'cash_images.id', '=', 'cash_image_bookings.cash_image_id')
            ->select('cash_image_bookings.booking_id')
            ->where('cash_images.interact_bank', $filters['interact_bank'])
            ->where('cash_images.relatable_type', 'App\Models\Booking')
            ->where('cash_images.relatable_id', 0)
            ->where('cash_images.data_verify', 1)
            ->when($filters['currency'], function($q) use ($filters) {
                $q->where('cash_images.currency', $filters['currency']);
            })
            ->pluck('booking_id')
            ->toArray();

        // Query for BookingItemGroup relatable_type
        $itemGroupBookingIds = DB::table('cash_images')
            ->join('booking_item_groups', 'cash_images.relatable_id', '=', 'booking_item_groups.id')
            ->select('booking_item_groups.booking_id')
            ->where('cash_images.interact_bank', $filters['interact_bank'])
            ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup')
            ->where('cash_images.relatable_id', '>', 0)
            ->where('cash_images.data_verify', 1)
            ->when($filters['currency'], function($q) use ($filters) {
                $q->where('cash_images.currency', $filters['currency']);
            })
            ->pluck('booking_id')
            ->toArray();

        // Merge and get unique booking IDs
        $bookingIds = array_unique(array_merge(
            $polymorphicBookingIds,
            $pivotBookingIds,
            $itemGroupBookingIds
        ));

        return $bookingIds;
    }

    /**
     * Calculate total sales from Booking cash images (deduplicated)
     */
    private function calculateTotalSales($filters)
    {
        // Get booking IDs that have cash images (revenue collected)
        $bookingIds = $this->getRelatedBookingIds($filters);

        if (empty($bookingIds)) {
            return 0;
        }

        // Calculate revenue from booking items
        $totalSales = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->whereIn('booking_items.booking_id', $bookingIds)
            ->where('booking_items.product_type', $filters['product_type'])
            ->where('booking_items.payment_status', $filters['booking_item_payment_status'])
            ->whereYear('booking_items.service_date', $filters['year'])
            ->whereMonth('booking_items.service_date', $filters['month_number'])
            ->when(!empty($filters['booking_payment_status']), function($q) use ($filters) {
                $q->where('bookings.payment_status', $filters['booking_payment_status']);
            })
            ->whereNull('booking_items.deleted_at')
            ->sum(DB::raw('CAST(booking_items.amount AS DECIMAL(10,2))'));

        return $totalSales ?? 0;
    }

    /**
     * Calculate total expense from BookingItemGroup cash images (deduplicated)
     */
    private function calculateTotalExpense($filters)
    {
        // Get booking IDs that have BookingItemGroup cash images (expenses paid)
        $itemGroupBookingIds = DB::table('cash_images')
            ->join('booking_item_groups', 'cash_images.relatable_id', '=', 'booking_item_groups.id')
            ->select('booking_item_groups.booking_id')
            ->where('cash_images.interact_bank', $filters['interact_bank'])
            ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup')
            ->where('cash_images.relatable_id', '>', 0)
            ->where('cash_images.data_verify', 1)
            ->when($filters['currency'], function($q) use ($filters) {
                $q->where('cash_images.currency', $filters['currency']);
            })
            ->distinct()
            ->pluck('booking_id')
            ->toArray();

        if (empty($itemGroupBookingIds)) {
            return 0;
        }

        // Calculate cost from booking items that have expense payments
        $totalExpense = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->whereIn('booking_items.booking_id', $itemGroupBookingIds)
            ->where('booking_items.product_type', $filters['product_type'])
            ->where('booking_items.payment_status', $filters['booking_item_payment_status'])
            ->whereYear('booking_items.service_date', $filters['year'])
            ->whereMonth('booking_items.service_date', $filters['month_number'])
            ->when(!empty($filters['booking_payment_status']), function($q) use ($filters) {
                $q->where('bookings.payment_status', $filters['booking_payment_status']);
            })
            ->whereNull('booking_items.deleted_at')
            ->sum(DB::raw('CAST(booking_items.total_cost_price AS DECIMAL(10,2))'));

        return $totalExpense ?? 0;
    }

    /**
     * Get product name by ID and type
     */
    private function getProductName($productId, $productType)
    {
        if (!$productId || !$productType) {
            return 'N/A';
        }

        try {
            $tableName = $this->getTableNameFromProductType($productType);

            if (!$tableName) {
                return 'N/A';
            }

            $product = DB::table($tableName)
                ->select('name')
                ->where('id', $productId)
                ->first();

            return $product ? $product->name : 'N/A';
        } catch (Exception $e) {
            Log::error('Error getting product name: ' . $e->getMessage());
            return 'N/A';
        }
    }

    /**
     * Get table name from product type
     */
    private function getTableNameFromProductType($productType)
    {
        $typeToTable = [
            'App\Models\Hotel' => 'hotels',
            'App\Models\EntranceTicket' => 'entrance_tickets',
            'App\Models\PrivateVanTour' => 'private_van_tours',
            'App\Models\Airline' => 'airlines',
            'App\Models\GroupTour' => 'group_tours',
        ];

        return $typeToTable[$productType] ?? null;
    }


    public function getBookingItemsByDate(Request $request)
    {
        try {
            $this->validateDateRequest($request);

            $filters = $this->extractDateFilters($request);

            // Step 1: Get ALL booking IDs from cash images
            $allBookingIdsFromCashImages = $this->getAllBookingIdsFromCashImages($filters);

            if (empty($allBookingIdsFromCashImages)) {
                return [
                    'success' => true,
                    'data' => [
                        'booking_items' => [],
                        'summary' => $this->getEmptySummary(),
                        'filters_applied' => $filters,
                    ],
                    'message' => 'No booking items found'
                ];
            }

            // Step 2: Filter booking IDs by service_date and product_type
            $filteredBookingIds = $this->filterBookingIdsByDateAndProduct(
                $allBookingIdsFromCashImages,
                $filters['date'],
                $filters['product_type']
            );

            if (empty($filteredBookingIds)) {
                return [
                    'success' => true,
                    'data' => [
                        'booking_items' => [],
                        'summary' => $this->getEmptySummary(),
                        'filters_applied' => $filters,
                    ],
                    'message' => 'No booking items found for this date'
                ];
            }

            // Step 3: Get booking items with full details
            $bookingItemsList = $this->getBookingItemsListForDate($filteredBookingIds, $filters);

            // Step 4: Calculate summary (revenue, cost, profit from booking items)
            $summary = $this->calculateSummaryForDate($filteredBookingIds, $filters);

            return [
                'success' => true,
                'data' => [
                    'booking_items' => $bookingItemsList,
                    'summary' => $summary,
                    'filters_applied' => $filters,
                ],
                'message' => 'Booking items retrieved successfully'
            ];

        } catch (InvalidArgumentException $e) {
            Log::error('Booking Items By Date Validation Error: ' . $e->getMessage());

            return [
                'success' => false,
                'data' => null,
                'message' => 'Validation Error: ' . $e->getMessage(),
                'error_type' => 'validation'
            ];
        } catch (Exception $e) {
            Log::error('Booking Items By Date Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return [
                'success' => false,
                'data' => null,
                'message' => 'An error occurred while retrieving booking items. Error: ' . $e->getMessage(),
                'error_type' => 'system'
            ];
        }
    }

    /**
     * Validate date request
     */
    private function validateDateRequest(Request $request)
    {
        $validator = Validator(
            $request->all(),
            [
                'date' => 'required|date_format:Y-m-d',
                'product_type' => 'required|in:' . implode(',', self::VALID_PRODUCT_TYPES),
                'interact_bank' => 'nullable|in:' . implode(',', self::VALID_INTERACT_BANK),
                'currency' => 'nullable|in:' . implode(',', self::VALID_CURRENCY),
                'booking_payment_status' => 'nullable|in:' . implode(',', self::VALID_PAYMENT_STATUS),
                'booking_item_payment_status' => 'nullable|in:' . implode(',', self::VALID_PAYMENT_STATUS),
            ]
        );

        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first());
        }
    }

    /**
     * Extract date filters from request
     */
    private function extractDateFilters(Request $request)
    {
        return [
            'date' => $request->input('date'), // Format: 2025-11-26
            'product_type' => $request->input('product_type'),
            'interact_bank' => $request->input('interact_bank'),
            'currency' => $request->input('currency', 'THB'),
            'booking_payment_status' => $request->input('booking_payment_status'),
            'booking_item_payment_status' => $request->input('booking_item_payment_status'),
        ];
    }

    /**
     * Get ALL booking IDs from cash images (for single date query)
     */
    private function getAllBookingIdsFromCashImages($filters)
    {
        // Get bookings from Booking cash images (polymorphic)
        $polymorphicBookingIds = DB::table('cash_images')
            ->select('relatable_id')
            ->where('relatable_type', 'App\Models\Booking')
            ->where('relatable_id', '>', 0)
            ->where('data_verify', 1)
            ->where('currency', $filters['currency'])
            ->pluck('relatable_id')
            ->toArray();

        // Get bookings from many-to-many relationship
        $pivotBookingIds = DB::table('cash_images')
            ->join('cash_image_bookings', 'cash_images.id', '=', 'cash_image_bookings.cash_image_id')
            ->select('cash_image_bookings.booking_id')
            ->where('cash_images.relatable_type', 'App\Models\Booking')
            ->where('cash_images.relatable_id', 0)
            ->where('cash_images.data_verify', 1)
            ->where('cash_images.currency', $filters['currency'])
            ->pluck('booking_id')
            ->toArray();

        // Get bookings from BookingItemGroup cash images
        $itemGroupBookingIds = DB::table('cash_images')
            ->join('booking_item_groups', 'cash_images.relatable_id', '=', 'booking_item_groups.id')
            ->select('booking_item_groups.booking_id')
            ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup')
            ->where('cash_images.relatable_id', '>', 0)
            ->where('cash_images.data_verify', 1)
            ->where('cash_images.currency', $filters['currency'])
            ->pluck('booking_id')
            ->toArray();

        // Merge and get unique booking IDs
        return array_unique(array_merge(
            $polymorphicBookingIds,
            $pivotBookingIds,
            $itemGroupBookingIds
        ));
    }

    /**
     * Filter booking IDs by date and product type
     */
    private function filterBookingIdsByDateAndProduct($bookingIds, $date, $productType)
    {
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
     * Get booking items list for specific date
     */
    private function getBookingItemsListForDate($bookingIds, $filters)
    {
        $query = DB::table('booking_items')
            ->select([
                'booking_items.id',
                'booking_items.booking_id',
                'booking_items.product_id',
                'booking_items.product_type',
                'booking_items.quantity',
                'booking_items.selling_price',
                'booking_items.amount',
                'booking_items.total_cost_price',
                'booking_items.discount',
                'booking_items.output_vat',
                'booking_items.commission',
                'booking_items.payment_status',
                'booking_items.service_date',
                'booking_items.days',
                'booking_items.comment',
                'bookings.crm_id',
                'bookings.booking_date',
                'bookings.payment_status as booking_payment_status',
                'bookings.customer_id',
                'customers.name as customer_name',
                'customers.phone_number as customer_phone',
                'customers.email as customer_email',
            ])
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->leftJoin('customers', 'bookings.customer_id', '=', 'customers.id')
            ->whereIn('booking_items.booking_id', $bookingIds)
            ->where('booking_items.product_type', $filters['product_type'])
            ->whereDate('booking_items.service_date', $filters['date'])
            ->whereNull('booking_items.deleted_at');

        // Apply booking payment status filter if provided
        if (!empty($filters['booking_payment_status'])) {
            $query->where('bookings.payment_status', $filters['booking_payment_status']);
        }

        // Apply booking item payment status filter if provided
        if (!empty($filters['booking_item_payment_status'])) {
            $query->where('booking_items.payment_status', $filters['booking_item_payment_status']);
        }

        $query->orderBy('booking_items.service_date')
              ->orderBy('booking_items.created_at');

        $items = $query->get();

        // Transform items
        $result = [];
        foreach ($items as $item) {
            $productName = $this->getProductName($item->product_id, $item->product_type);

            $revenue = $item->amount ?? 0;
            $cost = $item->total_cost_price ?? 0;
            $profit = $revenue - $cost;
            $profitMargin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

            $result[] = [
                'booking_item_id' => $item->id,
                'booking_id' => $item->booking_id,
                'crm_id' => $item->crm_id,
                'booking_date' => $item->booking_date,
                'booking_payment_status' => $item->booking_payment_status,
                'customer' => [
                    'id' => $item->customer_id,
                    'name' => $item->customer_name,
                    'phone_number' => $item->customer_phone,
                    'email' => $item->customer_email,
                ],
                'product_id' => $item->product_id,
                'product_type' => $item->product_type,
                'product_name' => $productName,
                'service_date' => $item->service_date,
                'quantity' => $item->quantity,
                'days' => $item->days,
                'selling_price' => $item->selling_price,
                'amount' => $revenue,
                'total_cost_price' => $cost,
                'discount' => $item->discount ?? 0,
                'output_vat' => $item->output_vat ?? 0,
                'commission' => $item->commission ?? 0,
                'payment_status' => $item->payment_status,
                'profit' => round($profit, 2),
                'profit_margin_percentage' => round($profitMargin, 2),
                'comment' => $item->comment,
            ];
        }

        return $result;
    }

    /**
     * Calculate summary for specific date (from booking items)
     */
    private function calculateSummaryForDate($bookingIds, $filters)
    {
        // Get booking IDs with expense payments
        $expensePaidBookingIds = DB::table('cash_images')
            ->join('booking_item_groups', 'cash_images.relatable_id', '=', 'booking_item_groups.id')
            ->select('booking_item_groups.booking_id')
            ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup')
            ->where('cash_images.relatable_id', '>', 0)
            ->where('cash_images.data_verify', 1)
            ->where('cash_images.currency', $filters['currency'])
            ->when($filters['interact_bank'], function($q) use ($filters) {
                $q->where('cash_images.interact_bank', $filters['interact_bank']);
            })
            ->distinct()
            ->pluck('booking_id')
            ->toArray();

        $query = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->whereIn('booking_items.booking_id', $bookingIds)
            ->where('booking_items.product_type', $filters['product_type'])
            ->whereDate('booking_items.service_date', $filters['date'])
            ->whereNull('booking_items.deleted_at');

        // Apply payment status filters if provided
        if (!empty($filters['booking_payment_status'])) {
            $query->where('bookings.payment_status', $filters['booking_payment_status']);
        }

        if (!empty($filters['booking_item_payment_status'])) {
            $query->where('booking_items.payment_status', $filters['booking_item_payment_status']);
        }

        $stats = $query->select(
            DB::raw('COUNT(DISTINCT booking_items.booking_id) as booking_count'),
            DB::raw('COUNT(booking_items.id) as booking_item_count'),
            DB::raw('SUM(booking_items.quantity) as total_quantity'),
            DB::raw('SUM(CAST(booking_items.amount AS DECIMAL(10,2))) as total_revenue'),
            DB::raw('SUM(CAST(booking_items.discount AS DECIMAL(10,2))) as total_discount'),
            DB::raw('SUM(CAST(booking_items.output_vat AS DECIMAL(10,2))) as total_vat'),
            DB::raw('SUM(CAST(booking_items.commission AS DECIMAL(10,2))) as total_commission')
        )->first();

        // Calculate cost only for items with expense payments
        $totalCost = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->whereIn('booking_items.booking_id', array_intersect($bookingIds, $expensePaidBookingIds))
            ->where('booking_items.product_type', $filters['product_type'])
            ->whereDate('booking_items.service_date', $filters['date'])
            ->whereNull('booking_items.deleted_at')
            ->when(!empty($filters['booking_payment_status']), function($q) use ($filters) {
                $q->where('bookings.payment_status', $filters['booking_payment_status']);
            })
            ->when(!empty($filters['booking_item_payment_status']), function($q) use ($filters) {
                $q->where('booking_items.payment_status', $filters['booking_item_payment_status']);
            })
            ->sum(DB::raw('CAST(booking_items.total_cost_price AS DECIMAL(10,2))'));

        $totalRevenue = $stats->total_revenue ?? 0;
        $totalCost = $totalCost ?? 0;
        $totalProfit = $totalRevenue - $totalCost;
        $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return [
            'booking_count' => $stats->booking_count ?? 0,
            'booking_item_count' => $stats->booking_item_count ?? 0,
            'total_quantity' => $stats->total_quantity ?? 0,
            'total_revenue' => round($totalRevenue, 2),
            'total_cost' => round($totalCost, 2),
            'total_discount' => round($stats->total_discount ?? 0, 2),
            'total_vat' => round($stats->total_vat ?? 0, 2),
            'total_commission' => round($stats->total_commission ?? 0, 2),
            'total_profit' => round($totalProfit, 2),
            'profit_margin_percentage' => round($profitMargin, 2),
        ];
    }

    /**
     * Get empty summary
     */
    private function getEmptySummary()
    {
        return [
            'booking_count' => 0,
            'booking_item_count' => 0,
            'total_quantity' => 0,
            'total_revenue' => 0,
            'total_cost' => 0,
            'total_discount' => 0,
            'total_vat' => 0,
            'total_commission' => 0,
            'total_profit' => 0,
            'profit_margin_percentage' => 0,
        ];
    }
}
