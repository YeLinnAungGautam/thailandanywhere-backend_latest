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

    /**
     * Get all receipts with filtering and pagination
     */
    public function getall(Request $request)
    {
        try {
            $this->validateRequest($request);

            $perPage = min((int) $request->get('per_page', self::PER_PAGE), self::MAX_PER_PAGE);
            $filters = $this->extractFilters($request);

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
     * Get unified results
     */
    private function getUnifiedResults($filters, $request, $perPage)
    {
        // Get ALL data from both tables
        $allData = $this->getAllUnifiedData();

        // Apply filters
        $filteredData = $this->applyFilters($allData, $filters);

        // Apply pagination
        $currentPage = (int) $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $total = $filteredData->count();

        $paginatedData = $filteredData->slice($offset, $perPage)->values();

        // Get summary
        $summary = $this->getSummary($allData, $filters);

        return [
            'data' => $paginatedData,
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
     * Get ALL data from both tables
     */
    private function getAllUnifiedData()
    {
        // Get ALL booking receipts
        $bookingReceipts = DB::table('booking_receipts as br')
            ->leftJoin('bookings as b', 'br.booking_id', '=', 'b.id')
            ->select([
                'br.id',
                DB::raw("'booking_receipt' as table_source"),
                'br.sender',
                'br.amount',
                'br.bank_name',
                'br.date',
                'br.created_at',
                'br.updated_at',
                'br.image as file_name',
                'br.booking_id',
                'b.crm_id'
            ])
            ->get();

        // Get ALL reservation expense receipts
        $expenseReceipts = DB::table('reservation_expense_receipts as rer')
            ->leftJoin('booking_items as bi', 'rer.booking_item_id', '=', 'bi.id')
            ->leftJoin('bookings as b', 'bi.booking_id', '=', 'b.id')
            ->select([
                'rer.id',
                DB::raw("'expense_receipt' as table_source"),
                DB::raw('NULL as sender'),
                'rer.amount',
                'rer.bank_name',
                'rer.date',
                'rer.created_at',
                'rer.updated_at',
                'rer.file as file_name',
                'b.id as booking_id',
                'b.crm_id'
            ])
            ->get();

        // Combine and format all data
        $allData = collect();

        foreach ($bookingReceipts as $item) {
            $allData->push($this->formatReceipt($item));
        }

        foreach ($expenseReceipts as $item) {
            $allData->push($this->formatReceipt($item));
        }

        // Sort by created_at desc
        return $allData->sortByDesc('created_at')->values();
    }

    /**
     * Apply filters to data
     */
    private function applyFilters($allData, $filters)
    {
        return $allData->filter(function ($item) use ($filters) {

            // 1. Type filter (complete/missing/all)
            if (!empty($filters['type']) && $filters['type'] !== 'all') {
                if (!$this->matchesType($item, $filters['type'])) {
                    return false;
                }
            }

            // 2. Sender filter (only for booking receipts)
            if (!empty($filters['sender'])) {
                if ($item['table_source'] !== 'booking_receipt' ||
                    empty($item['sender']) ||
                    stripos($item['sender'], $filters['sender']) === false) {
                    return false;
                }
            }

            // 3. Amount filter
            if (!empty($filters['amount'])) {
                if ((float)$item['amount'] != (float)$filters['amount']) {
                    return false;
                }
            }

            // 4. Bank name filter
            if (!empty($filters['bank_name'])) {
                if (empty($item['bank_name']) ||
                    stripos($item['bank_name'], $filters['bank_name']) === false) {
                    return false;
                }
            }

            // 5. CRM ID filter
            if (!empty($filters['crm_id'])) {
                if (empty($item['crm_id']) ||
                    stripos($item['crm_id'], $filters['crm_id']) === false) {
                    return false;
                }
            }

            // 6. Date filter (only for complete records or when specifically searching by date)
            if (!empty($filters['date'])) {
                // If filtering by missing type, don't apply date filter
                if (!empty($filters['type']) && $filters['type'] === 'missing') {
                    return true; // Skip date filter for missing records
                }

                if (!$this->matchesDate($item, $filters['date'])) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Check if record matches type (complete/missing)
     */
    private function matchesType($item, $type)
    {
        $hasAmount = !empty($item['amount']) && (float)$item['amount'] > 0;
        $hasBankName = !empty($item['bank_name']);
        $hasDate = !empty($item['date']) &&
                   $item['date'] !== '0000-00-00' &&
                   $item['date'] !== '1970-01-01';

        // For booking receipts, also check sender
        $hasSender = true;
        if ($item['table_source'] === 'booking_receipt') {
            $hasSender = !empty($item['sender']);
        }

        $isComplete = $hasAmount && $hasBankName && $hasDate && $hasSender;

        switch ($type) {
            case 'complete':
                return $isComplete;
            case 'missing':
                return !$isComplete;
            default:
                return true;
        }
    }

    /**
     * Check if record matches date filter
     */
    private function matchesDate($item, $dateFilter)
    {
        if (empty($item['date']) ||
            $item['date'] === '0000-00-00' ||
            $item['date'] === '1970-01-01') {
            return false;
        }

        $dateArray = array_map('trim', explode(',', $dateFilter));
        $itemDate = date('Y-m-d', strtotime($item['date']));

        if (count($dateArray) === 2) {
            // Date range
            $startDate = date('Y-m-d', strtotime($dateArray[0]));
            $endDate = date('Y-m-d', strtotime($dateArray[1]));
            return $itemDate >= $startDate && $itemDate <= $endDate;
        } else {
            // Single date
            $filterDate = date('Y-m-d', strtotime($dateArray[0]));
            return $itemDate === $filterDate;
        }
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
     * Get summary counts
     */
    private function getSummary($allData, $filters)
    {
        // Apply all filters except type
        $baseFilters = $filters;
        unset($baseFilters['type']);

        $filteredForSummary = $this->applyFilters($allData, $baseFilters);

        $bookingCount = $filteredForSummary->where('table_source', 'booking_receipt')->count();
        $expenseCount = $filteredForSummary->where('table_source', 'expense_receipt')->count();

        return [
            'booking_receipts' => $bookingCount,
            'expense_receipts' => $expenseCount,
            'total_records' => $bookingCount + $expenseCount
        ];
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
            'sender' => $request->input('sender'),
            'amount' => $request->input('amount'),
            'date' => $request->input('date'),
            'bank_name' => $request->input('bank_name'),
            'crm_id' => $request->input('crm_id')
        ];
    }
}
