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

    const VALID_TYPES = [
        'complete', 'missing', 'all'
    ];
    const VALID_INTERACT_BANK = [
        'personal', 'company', 'all', 'cash_at_office', 'to_money_changer', 'deposit_management'
    ];
    const VALID_CURRENCY = [
        'MMK', 'THB', 'USD'
    ];

    /**
     * Get all cash images with filtering and pagination
     */
    public function getAll(Request $request)
    {
        try {
            $this->validateRequest($request);

            $limit = min((int) $request->get('limit', self::PER_PAGE), self::MAX_PER_PAGE);
            $filters = $this->extractFilters($request);

            $query = $this->buildQuery($filters);
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
     * Validate request parameters
     */
    private function validateRequest(Request $request)
    {
        $validator = Validator(
            $request->all(),
            [
                'type' => 'nullable|in:' . implode(',', self::VALID_TYPES),
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
     * Build query with all filters applied
     */
    private function buildQuery($filters)
    {
        $query = CashImage::query();

        // Apply date filter
        if (!empty($filters['date'])) {
            $this->applyDateFilter($query, $filters['date']);
        }

        // Apply type filter (complete/missing)
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $this->applyTypeFilter($query, $filters['type']);
        }

        // Apply search filters
        $this->applySearchFilters($query, $filters);

        // Eager load relationships
        $query->with('relatable');

        // Order by date and created_at
        $query->orderBy('date', 'desc')->orderBy('created_at', 'desc');

        return $query;
    }

    /**
     * Apply date filter
     */
    private function applyDateFilter($query, $dateFilter)
    {
        $dates = array_map('trim', explode(',', $dateFilter));

        if (count($dates) === 2) {
            // Date range
            $startDate = $dates[0];
            $endDate = $dates[1];
            $query->whereBetween('date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        } else {
            // Single date
            $singleDate = $dates[0];
            $query->whereDate('date', $singleDate);
        }
    }

    /**
     * Apply type filter (complete/missing)
     */
    /**
     * Apply type filter (complete/missing)
     */
    private function applyTypeFilter($query, $type)
    {
        if ($type === 'complete') {
            // Records with all required fields filled AND relatable_id is not null
            $query->whereNotNull('relatable_id')
                  ->whereNotNull('amount')
                  ->where('amount', '>', 0)
                  ->whereNotNull('sender')
                  ->where('sender', '!=', '')
                  ->where('sender', '!=', 'NULL')
                  ->whereNotNull('receiver')
                  ->where('receiver', '!=', '')
                  ->where('receiver', '!=', 'NULL')
                  ->whereNotNull('date')
                  ->where('date', '!=', '')
                  ->whereNotNull('interact_bank')
                  ->where('interact_bank', '!=', 'null')
                  ->whereNotNull('currency')
                  ->where('currency', '!=', 'null');
        } elseif ($type === 'missing') {
            // Records missing required fields OR relatable_id is null
            $query->where(function ($q) {
                $q->whereNull('relatable_id')
                  ->orWhereNull('amount')
                  ->orWhere('amount', '<=', 0)
                  ->orWhereNull('sender')
                  ->orWhere('sender', '')
                  ->orWhere('sender', 'NULL')
                  ->orWhereNull('receiver')
                  ->orWhere('receiver', '')
                  ->orWhere('receiver', 'NULL')
                  ->orWhereNull('date')
                  ->orWhere('date', '')
                  ->orWhereNull('interact_bank')
                  ->orWhere('interact_bank', 'null')
                  ->orWhereNull('currency')
                  ->orWhere('currency', 'null');
            });
        }
    }

    /**
     * Apply search filters
     */
    private function applySearchFilters($query, $filters)
    {
        if (!empty($filters['sender'])) {
            $query->where('sender', 'like', '%' . $filters['sender'] . '%');
        }

        if (!empty($filters['reciever'])) {
            $query->where('receiver', 'like', '%' . $filters['reciever'] . '%');
        }

        if (!empty($filters['amount'])) {
            $query->where('amount', $filters['amount']); // Assuming exact match for amount
        }

        if (!empty($filters['interact_bank']) && $filters['interact_bank'] !== 'all') {
            $query->where('interact_bank', $filters['interact_bank']);
        }

        if (!empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        // Apply CRM ID filter if present
        if (!empty($filters['crm_id'])) {
            $this->applyCrmIdFilter($query, $filters['crm_id']);
        }
    }

    /**
     * Apply CRM ID filter through polymorphic relationships.
     * CRM ID only exists in the 'bookings' table.
     */
    private function applyCrmIdFilter($query, $crmId)
    {
        $query->where(function ($q) use ($crmId) {
            // Case 1: CashImage is directly related to a Booking
            $q->where(function ($subQ) use ($crmId) {
                $subQ->where('relatable_type', 'App\Models\Booking')
                     ->whereHas('relatable', function ($bookingQ) use ($crmId) {
                         $bookingQ->where('crm_id', 'like', '%' . $crmId . '%');
                     });
            });

            // Case 2: CashImage is related to a BookingItemGroup, which then relates to a Booking
            $q->orWhere(function ($subQ) use ($crmId) {
                $subQ->where('relatable_type', 'App\Models\BookingItemGroup')
                     ->whereExists(function ($existsQuery) use ($crmId) {
                         $existsQuery->select(DB::raw(1)) // Select 1 for existence check
                                   ->from('booking_item_groups') // Start from booking_item_groups
                                   ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id') // Join to bookings
                                   ->whereColumn('cash_images.relatable_id', 'booking_item_groups.id') // Match relatable_id to booking_item_group_id
                                   ->where('bookings.crm_id', 'like', '%' . $crmId . '%'); // Apply crm_id filter on bookings
                     });
            });
        });
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
            'receiver' => $request->input('reciever'),
            'sender' => $request->input('sender'),
            'amount' => $request->input('amount'),
            'date' => $request->input('date'),
            'crm_id' => $request->input('crm_id')
        ];
    }
}
