<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;
use InvalidArgumentException;

class ReceiptService
{
    const PER_PAGE = 10;
    const MAX_PER_PAGE = 100;

    const VALID_TYPES = [
        'complete', 'missing', 'all'
    ];
    const VALID_INTERACT_BANK = [
        'personal', 'company', 'all'
    ];

    /**
     * Get all receipts with filtering and pagination
     */
    public function getall(Request $request)
    {
        try {
            $this->validateRequest($request);

            $perPage = min((int) $request->get('per_page', self::PER_PAGE), self::MAX_PER_PAGE);
            $filters = $this->extractFilters($request);

            $results = $this->getOptimizedResults($filters, $request, $perPage);

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
     */
    private function validateRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:' . implode(',', self::VALID_TYPES),
            'interact_bank' => 'nullable|in:' . implode(',', self::VALID_INTERACT_BANK), // Add this
            'sender' => 'nullable|string|max:255',
            'reciever' => 'nullable|string|max:255', // Add this
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

        if ($request->date) {
            $this->validateDateFormat($request->date);
        }
    }

    /**
     * Validate date format
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
     * Get optimized results using database-level filtering
     */
    private function getOptimizedResults($filters, $request, $perPage)
    {
        $currentPage = (int) $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        // Build unified query with filters applied at database level
        $query = $this->buildUnifiedQuery($filters);

        // Get total count
        $total = $this->getTotalCount($filters);

        // Apply pagination and get results
        $results = $query->offset($offset)->limit($perPage)->get();

        // Format results
        $formattedData = $results->map(function ($item) {
            return $this->formatReceipt($item);
        });

        // Get summary
        $summary = $this->getOptimizedSummary($filters);

        return [
            'data' => $formattedData,
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
     * Build unified query with database-level filters
     */
    /**
     * Build unified query with database-level filters
     */
    private function buildUnifiedQuery($filters)
    {
        // Build booking receipts query
        $bookingQuery = DB::table('booking_receipts as br')
            ->leftJoin('bookings as b', 'br.booking_id', '=', 'b.id')
            ->select([
                'br.id',
                DB::raw("'booking_receipt' as table_source"),
                'br.sender',
                'br.reciever',        // Add this
                'br.interact_bank',   // Add this
                'br.amount',
                'br.bank_name',
                'br.date',
                'br.created_at',
                'br.updated_at',
                'br.image as file_name',
                'br.booking_id',
                'b.crm_id'
            ]);

        // Build expense receipts query
        $expenseQuery = DB::table('reservation_expense_receipts as rer')
            ->leftJoin('booking_items as bi', 'rer.booking_item_id', '=', 'bi.id')
            ->leftJoin('bookings as b', 'bi.booking_id', '=', 'b.id')
            ->select([
                'rer.id',
                DB::raw("'expense_receipt' as table_source"),
                'rer.sender',         // Add this
                'rer.reciever',       // Add this
                'rer.interact_bank',  // Add this
                'rer.amount',
                'rer.bank_name',
                'rer.date',
                'rer.created_at',
                'rer.updated_at',
                'rer.file as file_name',
                'b.id as booking_id',
                'b.crm_id'
            ]);

        // Apply filters to each query
        $bookingQuery = $this->applyDatabaseFilters($bookingQuery, $filters, 'booking');
        $expenseQuery = $this->applyDatabaseFilters($expenseQuery, $filters, 'expense');

        // Union the queries and order by created_at
        return $bookingQuery->union($expenseQuery)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Apply database-level filters
     */
    /**
     * Apply database-level filters
     */
    private function applyDatabaseFilters($query, $filters, $type)
    {
        // Basic filters that apply to both tables
        if (!empty($filters['amount'])) {
            $query->where($type === 'booking' ? 'br.amount' : 'rer.amount', $filters['amount']);
        }

        if (!empty($filters['bank_name'])) {
            $query->where($type === 'booking' ? 'br.bank_name' : 'rer.bank_name', 'like', '%' . $filters['bank_name'] . '%');
        }

        if (!empty($filters['crm_id'])) {
            $query->where('b.crm_id', 'like', '%' . $filters['crm_id'] . '%');
        }

        // Sender filter (applies to both tables now)
        if (!empty($filters['sender'])) {
            $query->where($type === 'booking' ? 'br.sender' : 'rer.sender', 'like', '%' . $filters['sender'] . '%');
        }

        // reciever filter (applies to both tables) - Add this
        if (!empty($filters['reciever'])) {
            $query->where($type === 'booking' ? 'br.reciever' : 'rer.reciever', 'like', '%' . $filters['reciever'] . '%');
        }

        // Interact bank filter (applies to both tables) - Add this
        if (!empty($filters['interact_bank']) && $filters['interact_bank'] !== 'all') {
            $query->where($type === 'booking' ? 'br.interact_bank' : 'rer.interact_bank', $filters['interact_bank']);
        }

        // Date filter
        if (!empty($filters['date']) && (!isset($filters['type']) || $filters['type'] !== 'missing')) {
            $this->applyDateFilter($query, $filters['date'], $type);
        }

        // Type filter (complete/missing)
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $this->applyTypeFilter($query, $filters['type'], $type);
        }

        return $query;
    }

    /**
     * Apply date filter at database level
     */
    private function applyDateFilter($query, $dateFilter, $type)
    {
        $dateField = $type === 'booking' ? 'br.date' : 'rer.date';
        $dateArray = array_map('trim', explode(',', $dateFilter));

        if (count($dateArray) === 2) {
            // Date range
            $startDate = date('Y-m-d', strtotime($dateArray[0]));
            $endDate = date('Y-m-d', strtotime($dateArray[1]));
            $query->whereBetween($dateField, [$startDate, $endDate]);
        } else {
            // Single date
            $filterDate = date('Y-m-d', strtotime($dateArray[0]));
            $query->whereDate($dateField, $filterDate);
        }

        // Exclude invalid dates
        $query->where($dateField, '!=', '0000-00-00')
              ->where($dateField, '!=', '1970-01-01')
              ->whereNotNull($dateField);
    }

    /**
     * Apply type filter at database level
     */
    /**
     * Apply type filter at database level
     */
    private function applyTypeFilter($query, $type, $tableType)
    {
        if ($type === 'complete') {
            // Records with all required fields
            if ($tableType === 'booking') {
                $query->whereNotNull('br.amount')
                      ->where('br.amount', '>', 0)
                      ->whereNotNull('br.bank_name')
                      ->where('br.bank_name', '!=', '')
                      ->whereNotNull('br.sender')
                      ->where('br.sender', '!=', '')
                      ->whereNotNull('br.reciever')        // Add receiver check
                      ->where('br.reciever', '!=', '')     // Add receiver check
                      ->whereNotNull('br.date')
                      ->where('br.date', '!=', '0000-00-00')
                      ->where('br.date', '!=', '1970-01-01');
            } else {
                $query->whereNotNull('rer.amount')
                      ->where('rer.amount', '>', 0)
                      ->whereNotNull('rer.bank_name')
                      ->where('rer.bank_name', '!=', '')
                      ->whereNotNull('rer.sender')         // Add sender check for expense
                      ->where('rer.sender', '!=', '')      // Add sender check for expense
                      ->whereNotNull('rer.reciever')       // Add receiver check
                      ->where('rer.reciever', '!=', '')    // Add receiver check
                      ->whereNotNull('rer.date')
                      ->where('rer.date', '!=', '0000-00-00')
                      ->where('rer.date', '!=', '1970-01-01');
            }
        } elseif ($type === 'missing') {
            // Records missing required fields
            if ($tableType === 'booking') {
                $query->where(function ($q) {
                    $q->whereNull('br.amount')
                      ->orWhere('br.amount', '<=', 0)
                      ->orWhereNull('br.bank_name')
                      ->orWhere('br.bank_name', '')
                      ->orWhereNull('br.sender')
                      ->orWhere('br.sender', '')
                      ->orWhereNull('br.reciever')         // Add receiver missing check
                      ->orWhere('br.reciever', '')         // Add receiver missing check
                      ->orWhereNull('br.date')
                      ->orWhere('br.date', '0000-00-00')
                      ->orWhere('br.date', '1970-01-01');
                });
            } else {
                $query->where(function ($q) {
                    $q->whereNull('rer.amount')
                      ->orWhere('rer.amount', '<=', 0)
                      ->orWhereNull('rer.bank_name')
                      ->orWhere('rer.bank_name', '')
                      ->orWhereNull('rer.sender')          // Add sender missing check
                      ->orWhere('rer.sender', '')          // Add sender missing check
                      ->orWhereNull('rer.reciever')        // Add receiver missing check
                      ->orWhere('rer.reciever', '')        // Add receiver missing check
                      ->orWhereNull('rer.date')
                      ->orWhere('rer.date', '0000-00-00')
                      ->orWhere('rer.date', '1970-01-01');
                });
            }
        }
    }

    /**
     * Get total count efficiently
     */
    private function getTotalCount($filters)
    {
        $bookingQuery = DB::table('booking_receipts as br')
            ->leftJoin('bookings as b', 'br.booking_id', '=', 'b.id');

        $expenseQuery = DB::table('reservation_expense_receipts as rer')
            ->leftJoin('booking_items as bi', 'rer.booking_item_id', '=', 'bi.id')
            ->leftJoin('bookings as b', 'bi.booking_id', '=', 'b.id');

        $bookingQuery = $this->applyDatabaseFilters($bookingQuery, $filters, 'booking');
        $expenseQuery = $this->applyDatabaseFilters($expenseQuery, $filters, 'expense');

        return $bookingQuery->count() + $expenseQuery->count();
    }

    /**
     * Get optimized summary
     */
    private function getOptimizedSummary($filters)
    {
        // Apply all filters except type for summary
        $baseFilters = $filters;
        unset($baseFilters['type']);

        $bookingQuery = DB::table('booking_receipts as br')
            ->leftJoin('bookings as b', 'br.booking_id', '=', 'b.id');

        $expenseQuery = DB::table('reservation_expense_receipts as rer')
            ->leftJoin('booking_items as bi', 'rer.booking_item_id', '=', 'bi.id')
            ->leftJoin('bookings as b', 'bi.booking_id', '=', 'b.id');

        $bookingQuery = $this->applyDatabaseFilters($bookingQuery, $baseFilters, 'booking');
        $expenseQuery = $this->applyDatabaseFilters($expenseQuery, $baseFilters, 'expense');

        $bookingCount = $bookingQuery->count();
        $expenseCount = $expenseQuery->count();

        return [
            'booking_receipts' => $bookingCount,
            'expense_receipts' => $expenseCount,
            'total_records' => $bookingCount + $expenseCount
        ];
    }

    /**
     * Format receipt data
     */
    private function formatReceipt($item)
    {
        return [
            'id' => $item->id,
            'table_source' => $item->table_source,
            'sender' => $item->sender,
            'reciever' => $item->reciever,
            'interact_bank' => $item->interact_bank,
            'amount' => $item->amount ? (float) $item->amount : null,
            'bank_name' => $item->bank_name,
            'date' => $item->date,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'file_url' => $this->generateFileUrl($item->file_name),
            'booking_id' => $item->booking_id,
            'crm_id' => $item->crm_id,
        ];
    }

    /**
     * Generate file URL
     */
    private function generateFileUrl($fileName)
    {
        if (!$fileName) {
            return null;
        }

        $path = 'images/' . $fileName;

        if (!Storage::exists($path)) {
            return null;
        }

        return Storage::url($path);
    }

    /**
     * Build pagination metadata
     */
    private function buildMetaData($paginatedData, $request)
    {
        $currentPage = $paginatedData['current_page'];
        $lastPage = $paginatedData['last_page'];
        $baseUrl = $request->url();

        $queryParams = $request->except('page');
        $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';

        $links = [];

        // Previous link
        $links[] = [
            'url' => $currentPage > 1 ? ($baseUrl . '?page=' . ($currentPage - 1) . $queryString) : null,
            'label' => '&laquo; Previous',
            'active' => false
        ];

        // Page links
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
        ];
    }

    /**
     * Generate page links
     */
    private function generatePageLinks($currentPage, $lastPage, $baseUrl, $queryString)
    {
        $links = [];
        $window = 2;

        if ($lastPage > 0) {
            $links[] = [
                'url' => $baseUrl . '?page=1' . $queryString,
                'label' => '1',
                'active' => $currentPage == 1
            ];
        }

        $start = max(2, $currentPage - $window);
        $end = min($lastPage - 1, $currentPage + $window);

        if ($start > 2) {
            $links[] = [
                'url' => null,
                'label' => '...',
                'active' => false
            ];
        }

        for ($i = $start; $i <= $end; $i++) {
            $links[] = [
                'url' => $baseUrl . '?page=' . $i . $queryString,
                'label' => (string)$i,
                'active' => $i == $currentPage
            ];
        }

        if ($end < $lastPage - 1) {
            $links[] = [
                'url' => null,
                'label' => '...',
                'active' => false
            ];
        }

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
     * Extract filters from request
     */
    private function extractFilters(Request $request)
    {
        return [
            'type' => $request->input('type'),
            'interact_bank' => $request->input('interact_bank'),
            'reciever' => $request->input('reciever'),
            'sender' => $request->input('sender'),
            'amount' => $request->input('amount'),
            'date' => $request->input('date'),
            'bank_name' => $request->input('bank_name'),
            'crm_id' => $request->input('crm_id')
        ];
    }
}
