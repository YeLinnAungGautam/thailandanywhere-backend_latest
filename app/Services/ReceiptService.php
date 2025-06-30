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
        'personal', 'company', 'all', 'cash_at_office','to_money_changer', 'deposit_management'
    ];
    const VALID_CURRENCY = [
        'MMK','THB','USD'
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
            'interact_bank' => 'nullable|in:' . implode(',', self::VALID_INTERACT_BANK),
            'currency' => 'nullable|in:' . implode(',', self::VALID_CURRENCY),
            'sender' => 'nullable|string|max:255',
            'reciever' => 'nullable|string|max:255',
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
    private function buildUnifiedQuery($filters)
    {
        // Build booking receipts query
        $bookingQuery = DB::table('booking_receipts as br')
            ->leftJoin('bookings as b', 'br.booking_id', '=', 'b.id')
            ->select([
                'br.id',
                DB::raw("'booking_receipt' as table_source"),
                'br.sender',
                'br.reciever',
                'br.interact_bank',
                'br.currency',
                'br.amount',
                'br.bank_name',
                'br.date',
                'br.created_at',
                'br.updated_at',
                'br.image as file_name',
                'br.booking_id',
                'b.crm_id'
            ]);

        // Build customer documents query for expense receipts
        $expenseQuery = DB::table('customer_documents as cd')
            ->leftJoin('booking_item_groups as big', 'cd.booking_item_group_id', '=', 'big.id')
            ->leftJoin('bookings as b', 'big.booking_id', '=', 'b.id')
            ->where('cd.type', 'expense_receipt')
            ->select([
                'cd.id',
                DB::raw("'expense_receipt' as table_source"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.sender')) as sender"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.reciever')) as reciever"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.interact_bank')) as interact_bank"),
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.currency')), 'THB') as currency"),
                DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.amount')) AS DECIMAL(10,2)) as amount"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.bank_name')) as bank_name"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date')) as date"),
                'cd.created_at',
                'cd.updated_at',
                'cd.file_name',
                'b.id as booking_id',
                'b.crm_id'
            ]);

        // Apply filters to each query
        $bookingQuery = $this->applyDatabaseFilters($bookingQuery, $filters, 'booking');
        $expenseQuery = $this->applyDatabaseFilters($expenseQuery, $filters, 'expense');

        // Union the queries and order by date DESC, then created_at DESC
        return DB::query()->fromSub(
            $bookingQuery->union($expenseQuery), 'combined_results'
        )->orderBy('date', 'desc')->orderBy('created_at', 'desc');
    }

    /**
     * Apply database-level filters
     */
    private function applyDatabaseFilters($query, $filters, $type)
    {
        // Basic filters that apply to both tables
        if (!empty($filters['amount'])) {
            if ($type === 'booking') {
                $query->where('br.amount', $filters['amount']);
            } else {
                $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.amount')) AS DECIMAL(10,2)) = ?", [$filters['amount']]);
            }
        }

        if (!empty($filters['bank_name'])) {
            if ($type === 'booking') {
                $query->where('br.bank_name', 'like', '%' . $filters['bank_name'] . '%');
            } else {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.bank_name')) LIKE ?", ['%' . $filters['bank_name'] . '%']);
            }
        }

        if (!empty($filters['crm_id'])) {
            $query->where('b.crm_id', 'like', '%' . $filters['crm_id'] . '%');
        }

        // Sender filter
        if (!empty($filters['sender'])) {
            if ($type === 'booking') {
                $query->where('br.sender', 'like', '%' . $filters['sender'] . '%');
            } else {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.sender')) LIKE ?", ['%' . $filters['sender'] . '%']);
            }
        }

        // Receiver filter
        if (!empty($filters['reciever'])) {
            if ($type === 'booking') {
                $query->where('br.reciever', 'like', '%' . $filters['reciever'] . '%');
            } else {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.reciever')) LIKE ?", ['%' . $filters['reciever'] . '%']);
            }
        }

        // Interact bank filter
        if (!empty($filters['interact_bank']) && $filters['interact_bank'] !== 'all') {
            if ($type === 'booking') {
                $query->where('br.interact_bank', $filters['interact_bank']);
            } else {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.interact_bank')) = ?", [$filters['interact_bank']]);
            }
        }

        // Currency filter
        if (!empty($filters['currency'])) {
            if ($type === 'booking') {
                $query->where('br.currency', $filters['currency']);
            } else {
                $query->whereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.currency')), 'THB') = ?", [$filters['currency']]);
            }
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
        $dateArray = array_map('trim', explode(',', $dateFilter));

        if (count($dateArray) === 2) {
            // Date range
            $startDate = date('Y-m-d', strtotime($dateArray[0]));
            $endDate = date('Y-m-d', strtotime($dateArray[1]));

            if ($type === 'booking') {
                $query->whereBetween('br.date', [$startDate, $endDate]);
            } else {
                $query->whereRaw("DATE(JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date'))) BETWEEN ? AND ?", [$startDate, $endDate]);
            }
        } else {
            // Single date
            $filterDate = date('Y-m-d', strtotime($dateArray[0]));

            if ($type === 'booking') {
                $query->whereDate('br.date', $filterDate);
            } else {
                $query->whereRaw("DATE(JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date'))) = ?", [$filterDate]);
            }
        }

        // Exclude invalid dates
        if ($type === 'booking') {
            $query->where('br.date', '!=', '0000-00-00')
                  ->where('br.date', '!=', '1970-01-01')
                  ->whereNotNull('br.date');
        } else {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date')) != '0000-00-00'")
                  ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date')) != '1970-01-01'")
                  ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date')) IS NOT NULL");
        }
    }

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
                      ->whereNotNull('br.reciever')
                      ->where('br.reciever', '!=', '')
                      ->whereNotNull('br.date')
                      ->where('br.date', '!=', '0000-00-00')
                      ->where('br.date', '!=', '1970-01-01');
            } else {
                $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.amount')) AS DECIMAL(10,2)) > 0")
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.bank_name')) IS NOT NULL")
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.bank_name')) != ''")
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.sender')) IS NOT NULL")
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.sender')) != ''")
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.reciever')) IS NOT NULL")
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.reciever')) != ''")
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date')) IS NOT NULL")
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date')) != '0000-00-00'")
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date')) != '1970-01-01'");
            }
        } elseif ($type === 'missing') {
            // Records missing required fields with booking date restrictions
            if ($tableType === 'booking') {
                $query->where(function ($q) {
                    $q->whereNull('br.amount')
                      ->orWhere('br.amount', '<=', 0)
                      ->orWhereNull('br.bank_name')
                      ->orWhere('br.bank_name', '')
                      ->orWhereNull('br.sender')
                      ->orWhere('br.sender', '')
                      ->orWhereNull('br.reciever')
                      ->orWhere('br.reciever', '')
                      ->orWhereNull('br.date')
                      ->orWhere('br.date', '0000-00-00')
                      ->orWhere('br.date', '1970-01-01');
                })
                // Only show bookings from Nov 2024 onwards
                ->where('b.booking_date', '>=', '2024-11-01');
            } else {
                $query->where(function ($q) {
                    $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.amount')) IS NULL")
                      ->orWhereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.amount')) AS DECIMAL(10,2)) <= 0")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.bank_name')) IS NULL")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.bank_name')) = ''")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.sender')) IS NULL")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.sender')) = ''")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.reciever')) IS NULL")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.reciever')) = ''")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date')) IS NULL")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date')) = '0000-00-00'")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(cd.meta, '$.date')) = '1970-01-01'");
                })
                // Only show bookings from Nov 2024 onwards
                ->where('b.booking_date', '>=', '2024-11-01');
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

        $expenseQuery = DB::table('customer_documents as cd')
            ->leftJoin('booking_item_groups as big', 'cd.booking_item_group_id', '=', 'big.id')
            ->leftJoin('bookings as b', 'big.booking_id', '=', 'b.id')
            ->where('cd.type', 'expense_receipt');

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

        $expenseQuery = DB::table('customer_documents as cd')
            ->leftJoin('booking_item_groups as big', 'cd.booking_item_group_id', '=', 'big.id')
            ->leftJoin('bookings as b', 'big.booking_id', '=', 'b.id')
            ->where('cd.type', 'expense_receipt');

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
            'currency' => $item->currency,
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
            'currency' => $request->input('currency'),
            'reciever' => $request->input('reciever'),
            'sender' => $request->input('sender'),
            'amount' => $request->input('amount'),
            'date' => $request->input('date'),
            'bank_name' => $request->input('bank_name'),
            'crm_id' => $request->input('crm_id')
        ];
    }
}
