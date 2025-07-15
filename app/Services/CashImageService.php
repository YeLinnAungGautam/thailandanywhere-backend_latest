<?php

namespace App\Services;

use App\Http\Resources\Accountance\CashImageListResource as AccountanceCashImageResource;
use App\Models\CashImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;
use InvalidArgumentException;

class CashImageService
{
    const PER_PAGE = 10;
    const MAX_PER_PAGE = 100;

    const VALID_INTERACT_BANK = [
        'personal', 'company', 'all', 'cash_at_office', 'to_money_changer', 'deposit_management'
    ];
    const VALID_CURRENCY = [
        'MMK', 'THB', 'USD'
    ];

    /**
     * Get all cash images with filtering and pagination (Optimized)
     */
    public function getAll(Request $request)
    {
        try {
            $this->validateRequest($request);

            $limit = min((int) $request->get('limit', self::PER_PAGE), self::MAX_PER_PAGE);
            $filters = $this->extractFilters($request);

            $query = $this->buildOptimizedQuery($filters);
            $data = $query->paginate($limit);

            $resourceCollection = AccountanceCashImageResource::collection($data);

            return [
                'success' => true,
                'data' => $resourceCollection->response()->getData(true),
                'message' => 'Cash images retrieved successfully'
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
                'message' => 'An error occurred while retrieving cash images. Error: ' . $e->getMessage(),
                'error_type' => 'system'
            ];
        }
    }

    /**
     * Validate request parameters (Simplified - removed type validation)
     */
    private function validateRequest(Request $request)
    {
        $validator = Validator(
            $request->all(),
            [
                'interact_bank' => 'nullable|in:' . implode(',', self::VALID_INTERACT_BANK),
                'currency' => 'nullable|in:' . implode(',', self::VALID_CURRENCY),
                'sender' => 'nullable|string|max:255',
                'receiver' => 'nullable|string|max:255',
                'amount' => 'nullable|numeric|min:0',
                'date' => 'nullable|string',
                'crm_id' => 'nullable|string|max:255',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:' . self::MAX_PER_PAGE
            ]
        );

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
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString) || !strtotime($dateString)) {
                throw new InvalidArgumentException("Invalid date format: {$dateString}. Expected YYYY-MM-DD.");
            }
        }

        if (count($dates) > 2) {
            throw new InvalidArgumentException("Date filter supports maximum 2 dates for range");
        }
    }

    /**
     * Build optimized query without relatable and type filtering
     */
    private function buildOptimizedQuery($filters)
    {
        // Select only necessary columns for better performance
        // Removed 'file' column as it doesn't exist in the database
        $query = CashImage::select([
            'id',
            'date',
            'sender',
            'receiver',
            'amount',
            'interact_bank',
            'currency',
            'image',
            'created_at',
            'updated_at',
            'relatable_id',
            'relatable_type'
            // Removed relatable_id and relatable_type to avoid relationship loading
        ]);

        // Apply date filter
        if (!empty($filters['date'])) {
            $this->applyDateFilter($query, $filters['date']);
        }

        // Apply search filters (simplified)
        $this->applySimpleSearchFilters($query, $filters);

        // Add database indexes for these columns if not already present:
        // ALTER TABLE cash_images ADD INDEX idx_date_created (date, created_at);
        // ALTER TABLE cash_images ADD INDEX idx_interact_bank (interact_bank);
        // ALTER TABLE cash_images ADD INDEX idx_currency (currency);
        // ALTER TABLE cash_images ADD INDEX idx_sender (sender);
        // ALTER TABLE cash_images ADD INDEX idx_receiver (receiver);

        // Order by date and created_at (ensure these columns are indexed)
        $query->orderBy('date', 'desc')->orderBy('created_at', 'desc');

        return $query;
    }

    /**
     * Apply date filter (optimized)
     */
    private function applyDateFilter($query, $dateFilter)
    {
        $dates = array_map('trim', explode(',', $dateFilter));

        if (count($dates) === 2) {
            // Date range - using DATE() function for better index usage
            $startDate = $dates[0];
            $endDate = $dates[1];
            $query->whereDate('date', '>=', $startDate)
                  ->whereDate('date', '<=', $endDate);
        } else {
            // Single date
            $singleDate = $dates[0];
            $query->whereDate('date', $singleDate);
        }
    }

    /**
     * Apply simplified search filters (removed complex type filtering)
     */
    private function applySimpleSearchFilters($query, $filters)
    {
        if (!empty($filters['sender'])) {
            $query->where('sender', 'like', '%' . $filters['sender'] . '%');
        }

        if (!empty($filters['receiver'])) {
            $query->where('receiver', 'like', '%' . $filters['receiver'] . '%');
        }

        if (!empty($filters['amount'])) {
            $query->where('amount', $filters['amount']);
        }

        if (!empty($filters['interact_bank']) && $filters['interact_bank'] !== 'all') {
            $query->where('interact_bank', $filters['interact_bank']);
        }

        if (!empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        // Simplified CRM ID filter (if still needed - this might also be slow)
        if (!empty($filters['crm_id'])) {
            // Option 1: Remove this entirely for better performance
            // Option 2: If you must keep it, add proper indexes
            $this->applySimpleCrmIdFilter($query, $filters['crm_id']);
        }
    }

    /**
     * Simplified CRM ID filter (optional - consider removing entirely)
     */
    private function applySimpleCrmIdFilter($query, $crmId)
    {
        // This is still potentially slow - consider removing if not essential
        // Or create a denormalized column for crm_id in cash_images table
        $query->where(function ($q) use ($crmId) {
            $q->where('relatable_type', 'App\Models\Booking')
              ->whereExists(function ($existsQuery) use ($crmId) {
                  $existsQuery->select(DB::raw(1))
                            ->from('bookings')
                            ->whereColumn('bookings.id', 'cash_images.relatable_id')
                            ->where('bookings.crm_id', 'like', '%' . $crmId . '%');
              });
        });
    }

    /**
     * Extract filters from request (removed type)
     */
    private function extractFilters(Request $request)
    {
        return [
            'interact_bank' => $request->input('interact_bank'),
            'currency' => $request->input('currency'),
            'receiver' => $request->input('receiver'),
            'sender' => $request->input('sender'),
            'amount' => $request->input('amount'),
            'date' => $request->input('date'),
            'crm_id' => $request->input('crm_id') // Consider removing this too for max performance
        ];
    }

    /**
     * Alternative: Get basic list without any complex filtering
     */
    public function getBasicList(Request $request)
    {
        try {
            $limit = min((int) $request->get('limit', self::PER_PAGE), self::MAX_PER_PAGE);

            $query = CashImage::select([
                'id',
                'date',
                'sender',
                'receiver',
                'amount',
                'interact_bank',
                'currency',
                'created_at',
                'image',
                'relatable_type',
                'relatable_id'
            ])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

            // Only apply date filter if provided (most common filter)
            if ($request->filled('date')) {
                $query->whereDate('date', $request->input('date'));
            }

            $data = $query->paginate($limit);

            return [
                'success' => true,
                'data' => $data,
                'message' => 'Cash images retrieved successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'An error occurred: ' . $e->getMessage()
            ];
        }
    }
}

/*
Database Optimization Recommendations:

1. Add these indexes for better performance:
ALTER TABLE cash_images ADD INDEX idx_date_created (date, created_at);
ALTER TABLE cash_images ADD INDEX idx_interact_bank (interact_bank);
ALTER TABLE cash_images ADD INDEX idx_currency (currency);
ALTER TABLE cash_images ADD INDEX idx_sender (sender(50)); -- Partial index for string columns
ALTER TABLE cash_images ADD INDEX idx_receiver (receiver(50));

2. Consider adding a denormalized crm_id column to cash_images table to avoid joins:
ALTER TABLE cash_images ADD COLUMN crm_id VARCHAR(255) NULL;
ALTER TABLE cash_images ADD INDEX idx_crm_id (crm_id);

3. If you need the relatable functionality, consider lazy loading:
- Load basic data first
- Load relationships on demand via AJAX calls

4. Implement caching for frequently accessed data:
- Cache pagination results for 5-10 minutes
- Use Redis or Memcached

5. Consider using database views for complex queries
*/
