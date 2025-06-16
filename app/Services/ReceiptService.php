<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\ReservationExpenseReceipt;
use App\Models\BookingReceipt;
use Exception;
use InvalidArgumentException;
use Illuminate\Support\Collection;

class ReceiptService
{
    const PER_PAGE = 10;
    const MAX_PER_PAGE = 100;

    const VALID_TYPES = ['complete', 'incomplete', 'missing', 'all'];
    const VALID_RECEIPT_TYPES = ['customer_payment', 'expense', 'all'];

    /**
     * Get all receipts with filtering and pagination
     *
     * @param Request $request
     * @return array
     */
    public function getall(Request $request)
    {
        try {
            // Validate input parameters
            $this->validateRequest($request);

            $perPage = min((int) $request->get('per_page', self::PER_PAGE), self::MAX_PER_PAGE);
            $filters = $this->extractFilters($request);

            // Use Union query for better performance
            $results = $this->getUnifiedResults($filters, $request, $perPage);

            return [
                'success' => true,
                'data' => [
                    'data' => $results['data'],
                    'meta' => $this->buildMetaData($results, $request),
                    'summary' => $results['summary']
                ],
                'message' => 'All receipts retrieved successfully'
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'Validation Error: ' . $e->getMessage(),
                'error_type' => 'validation'
            ];
        } catch (Exception $e) {
            // \Log::error('ReceiptService::getall Error: ' . $e->getMessage(), [
            //     'request' => $request->all(),
            //     'trace' => $e->getTraceAsString()
            // ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'An error occurred while retrieving receipts. Error: ' . $e->getMessage(),
                'error_type' => 'system'
            ];
        }
    }

    /**
     * Validate request parameters
     *
     * @param Request $request
     * @throws InvalidArgumentException
     */
    private function validateRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:' . implode(',', self::VALID_TYPES),
            'receipt_type' => 'nullable|in:' . implode(',', self::VALID_RECEIPT_TYPES),
            'sender' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'date' => 'nullable|string',
            'bank_name' => 'nullable|string|max:255',
            'crm_id' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:' . self::MAX_PER_PAGE
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first());
        }

        // Validate date format if provided
        if ($request->date) {
            $this->validateDateFormat($request->date);
        }
    }

    /**
     * Validate date format
     *
     * @param string $date
     * @throws InvalidArgumentException
     */
    private function validateDateFormat($date)
    {
        $dates = explode(',', $date);

        foreach ($dates as $dateString) {
            $dateString = trim($dateString);
            if (!strtotime($dateString)) {
                throw new InvalidArgumentException("Invalid date format: {$dateString}");
            }
        }

        if (count($dates) > 2) {
            throw new InvalidArgumentException("Date filter supports maximum 2 dates for range");
        }
    }

    /**
     * Get unified results using Union query for better performance
     *
     * @param array $filters
     * @param Request $request
     * @param int $perPage
     * @return array
     */
    private function getUnifiedResults($filters, $request, $perPage)
    {
        // Get current page
        $currentPage = (int) $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        // Build union query
        $unionQuery = $this->buildUnionQuery($filters);

        // Get total count
        $totalQuery = DB::query()
            ->fromSub($unionQuery, 'combined_receipts');
        $total = $totalQuery->count();

        // Apply pagination and ordering
        $paginatedResults = DB::query()
            ->fromSub($unionQuery, 'combined_receipts')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc') // Secondary sort for consistency
            ->offset($offset)
            ->limit($perPage)
            ->get();

        // Get summary counts
        $summary = $this->getSummary($filters);

        return [
            'data' => $paginatedResults->map(function($item) {
                return $this->formatUnifiedReceipt($item);
            }),
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, ceil($total / $perPage)),
            'from' => $total > 0 ? $offset + 1 : null,
            'to' => min($offset + $perPage, $total),
            'summary' => $summary
        ];
    }

    /**
     * Build union query for both receipt types
     * Fixed to match actual table structure
     *
     * @param array $filters
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildUnionQuery($filters)
    {
        // Booking receipts query (booking_receipts table)
        // Columns: id, booking_id, image, amount, date, bank_name, sender, is_corporate, note, deleted_at, created_at, updated_at
        $bookingQuery = DB::table('booking_receipts as br')
            ->leftJoin('bookings as b', 'br.booking_id', '=', 'b.id')
            ->select([
                'br.id',
                DB::raw("'BookingReceipt' as table_source"),
                'br.sender',
                'br.amount',
                'br.bank_name',
                'br.date',
                'br.created_at',
                'br.updated_at',
                'br.image as image_file',
                DB::raw("'customer_payment' as receipt_type"),
                'br.booking_id',
                DB::raw('NULL as reservation_id'),
                'br.booking_id as booking_item_id', // Using booking_id as booking_item_id
                'b.crm_id'
            ])
            ->whereNull('br.deleted_at'); // Only non-deleted records

        // Reservation expense receipts query (reservation_expense_receipts table)
        // Columns: id, booking_item_id, file, created_at, updated_at, amount, bank_name, date, is_corporate, comment
        $reservationQuery = DB::table('reservation_expense_receipts as rer')
            ->leftJoin('booking_items as bi', 'rer.booking_item_id', '=', 'bi.id')
            ->leftJoin('bookings as b', 'bi.booking_id', '=', 'b.id')
            ->select([
                'rer.id',
                DB::raw("'ReservationExpenseReceipt' as table_source"),
                DB::raw('NULL as sender'),
                'rer.amount',
                'rer.bank_name',
                'rer.date',
                'rer.created_at',
                'rer.updated_at',
                'rer.file as image_file',
                DB::raw("'expense' as receipt_type"),
                'b.id as booking_id', // Get booking_id through booking_items
                DB::raw('NULL as reservation_id'),
                'rer.booking_item_id',
                'b.crm_id'
            ]);

        // Apply filters to both queries
        $this->applyFiltersToQuery($bookingQuery, $filters, true);  // true = includeSender
        $this->applyFiltersToQuery($reservationQuery, $filters, false); // false = no sender field

        // Apply receipt type filter
        if (!empty($filters['receipt_type']) && $filters['receipt_type'] !== 'all') {
            if ($filters['receipt_type'] === 'expense') {
                return $reservationQuery;
            } elseif ($filters['receipt_type'] === 'customer_payment') {
                return $bookingQuery;
            }
        }

        // Union both queries
        return $bookingQuery->union($reservationQuery);
    }

    /**
     * Apply filters to query
     * Updated to match actual table structure
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $filters
     * @param bool $includeSender
     */
    private function applyFiltersToQuery($query, $filters, $includeSender)
    {
        // Type filter (complete/incomplete/missing)
        $this->applyTypeFilter($query, $filters['type'] ?? null, $includeSender);

        // Sender filter (only for booking receipts)
        if ($includeSender && !empty($filters['sender'])) {
            $query->where('br.sender', 'like', '%' . $filters['sender'] . '%');
        }

        // Amount filter
        if (!empty($filters['amount'])) {
            $tableAlias = $includeSender ? 'br' : 'rer';
            $query->where("{$tableAlias}.amount", $filters['amount']);
        }

        // Bank name filter
        if (!empty($filters['bank_name'])) {
            $tableAlias = $includeSender ? 'br' : 'rer';
            $query->where("{$tableAlias}.bank_name", 'like', '%' . $filters['bank_name'] . '%');
        }

        // Date filter
        if (!empty($filters['date'])) {
            $this->applyDateFilter($query, $filters['date'], $includeSender);
        }

        // CRM ID filter
        if (!empty($filters['crm_id'])) {
            $query->where('b.crm_id', 'like', '%' . $filters['crm_id'] . '%');
        }
    }

    /**
     * Apply type filter with improved logic
     * Updated to use correct table aliases
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string|null $type
     * @param bool $includeSender
     */
    private function applyTypeFilter($query, $type, $includeSender)
    {
        if (empty($type) || $type === 'all') {
            return;
        }

        $tableAlias = $includeSender ? 'br' : 'rer';
        $requiredFields = ["{$tableAlias}.amount", "{$tableAlias}.bank_name", "{$tableAlias}.date"];

        if ($includeSender) {
            $requiredFields[] = "{$tableAlias}.sender";
        }

        switch($type) {
            case 'complete':
                foreach ($requiredFields as $field) {
                    $query->whereNotNull($field)
                          ->where($field, '!=', '')
                          ->where($field, '!=', '0');
                }
                break;

            case 'incomplete':
            case 'missing':
                $query->where(function($subQuery) use ($requiredFields) {
                    foreach ($requiredFields as $field) {
                        $subQuery->orWhereNull($field)
                                ->orWhere($field, '')
                                ->orWhere($field, '0');
                    }
                });
                break;
        }
    }

    /**
     * Apply date filter with improved logic
     * Updated to use correct table aliases
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $date
     * @param bool $includeSender
     */
    private function applyDateFilter($query, $date, $includeSender)
    {
        if (empty($date)) {
            return;
        }

        $tableAlias = $includeSender ? 'br' : 'rer';
        $dateField = "{$tableAlias}.date";
        $dateArray = array_map('trim', explode(',', $date));

        if (count($dateArray) === 2) {
            // Date range
            $startDate = $dateArray[0];
            $endDate = $dateArray[1];

            $query->whereDate($dateField, '>=', $startDate)
                  ->whereDate($dateField, '<=', $endDate);
        } else {
            // Single date
            $query->whereDate($dateField, $dateArray[0]);
        }
    }

    /**
     * Get summary counts
     * Updated to match actual table structure
     *
     * @param array $filters
     * @return array
     */
    private function getSummary($filters)
    {
        // Remove type and receipt_type filters for total counts
        $summaryFilters = array_filter($filters, function($value, $key) {
            return !in_array($key, ['type', 'receipt_type']) && !is_null($value);
        }, ARRAY_FILTER_USE_BOTH);

        // Get booking receipts count
        $bookingQuery = DB::table('booking_receipts as br')
            ->leftJoin('bookings as b', 'br.booking_id', '=', 'b.id')
            ->whereNull('br.deleted_at');
        $this->applyFiltersToQuery($bookingQuery, $summaryFilters, true);
        $bookingCount = $bookingQuery->count();

        // Get reservation expense receipts count
        $reservationQuery = DB::table('reservation_expense_receipts as rer')
            ->leftJoin('booking_items as bi', 'rer.booking_item_id', '=', 'bi.id')
            ->leftJoin('bookings as b', 'bi.booking_id', '=', 'b.id');
        $this->applyFiltersToQuery($reservationQuery, $summaryFilters, false);
        $reservationCount = $reservationQuery->count();

        return [
            'reservation_expense_receipts' => $reservationCount,
            'booking_receipts' => $bookingCount,
            'total_records' => $reservationCount + $bookingCount
        ];
    }

    /**
     * Format unified receipt data
     *
     * @param object $item
     * @return array
     */
    private function formatUnifiedReceipt($item)
    {
        return [
            'id' => $item->id,
            'table_source' => $item->table_source,
            'sender' => $item->sender,
            'amount' => $item->amount ? (float) $item->amount : null,
            'bank_name' => $item->bank_name,
            'date' => $item->date,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'receipt_url' => $this->generateReceiptUrl($item),
            'receipt_type' => $item->receipt_type,
            'booking_id' => $item->booking_id,
            'reservation_id' => $item->reservation_id,
            'booking_item_id' => $item->booking_item_id,
            'crm_id' => $item->crm_id,
        ];
    }

    /**
     * Generate receipt URL
     *
     * @param object $item
     * @return string|null
     */
    private function generateReceiptUrl($item)
    {
        if (!$item->image_file) {
            return null;
        }

        $path = 'images/' . $item->image_file;

        // Check if file exists
        if (!Storage::exists($path)) {
            return null;
        }

        return Storage::url($path);
    }

    /**
     * Build improved metadata with proper query parameter handling
     *
     * @param array $paginatedData
     * @param Request $request
     * @return array
     */
    private function buildMetaData($paginatedData, $request)
    {
        $currentPage = $paginatedData['current_page'];
        $lastPage = $paginatedData['last_page'];
        $baseUrl = $request->url();

        // Get all query parameters except page
        $queryParams = $request->except('page');
        $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';

        $links = [];

        // Previous link
        $links[] = [
            'url' => $currentPage > 1 ? ($baseUrl . '?page=' . ($currentPage - 1) . $queryString) : null,
            'label' => '&laquo; Previous',
            'active' => false
        ];

        // Page links with intelligent windowing
        $links = array_merge($links, $this->generatePageLinks($currentPage, $lastPage, $baseUrl, $queryString));

        // Next link
        $links[] = [
            'url' => $currentPage < $lastPage ? ($baseUrl . '?page=' . ($currentPage + 1) . $queryString) : null,
            'label' => 'Next &raquo;',
            'active' => false
        ];

        return [
            'current_page' => $currentPage,
            'from' => $paginatedData['from'],
            'last_page' => $lastPage,
            'links' => $links,
            'path' => $baseUrl,
            'per_page' => $paginatedData['per_page'],
            'to' => $paginatedData['to'],
            'total' => $paginatedData['total'],
            'total_page' => $lastPage,
        ];
    }

    /**
     * Generate page links with intelligent windowing
     *
     * @param int $currentPage
     * @param int $lastPage
     * @param string $baseUrl
     * @param string $queryString
     * @return array
     */
    private function generatePageLinks($currentPage, $lastPage, $baseUrl, $queryString)
    {
        $links = [];
        $window = 2; // Show 2 pages on each side of current page

        // Always show first page
        if ($lastPage > 0) {
            $links[] = [
                'url' => $baseUrl . '?page=1' . $queryString,
                'label' => '1',
                'active' => $currentPage == 1
            ];
        }

        // Calculate window range
        $start = max(2, $currentPage - $window);
        $end = min($lastPage - 1, $currentPage + $window);

        // Add ellipsis before window if needed
        if ($start > 2) {
            $links[] = [
                'url' => null,
                'label' => '...',
                'active' => false
            ];
        }

        // Add pages in window
        for ($i = $start; $i <= $end; $i++) {
            $links[] = [
                'url' => $baseUrl . '?page=' . $i . $queryString,
                'label' => (string)$i,
                'active' => $i == $currentPage
            ];
        }

        // Add ellipsis after window if needed
        if ($end < $lastPage - 1) {
            $links[] = [
                'url' => null,
                'label' => '...',
                'active' => false
            ];
        }

        // Always show last page (if different from first)
        if ($lastPage > 1) {
            $links[] = [
                'url' => $baseUrl . '?page=' . $lastPage . $queryString,
                'label' => (string)$lastPage,
                'active' => $currentPage == $lastPage
            ];
        }

        return $links;
    }

    /**
     * Extract and sanitize filters from request
     *
     * @param Request $request
     * @return array
     */
    private function extractFilters(Request $request)
    {
        return [
            'type' => $request->input('type'),
            'receipt_type' => $request->input('receipt_type'),
            'sender' => $request->input('sender'),
            'amount' => $request->input('amount'),
            'date' => $request->input('date'),
            'bank_name' => $request->input('bank_name'),
            'crm_id' => $request->input('crm_id')
        ];
    }
}
