<?php

namespace App\Services;

use App\Http\Resources\Accountance\CashImageListResource as AccountanceCashImageResource;
use App\Models\CashImage;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CashImagePartnerService
{
    const PER_PAGE = 10;
    const MAX_PER_PAGE = 100;

    const VALID_INTERACT_BANK = [
        'personal', 'company', 'all', 'cash_at_office', 'to_money_changer', 'deposit_management', 'pay_to_driver'
    ];

    const VALID_CURRENCY = [
        'MMK', 'THB', 'USD'
    ];

    const VALID_PRODUCT_TYPES = [
        'App\Models\Hotel',
        'App\Models\EntranceTicket',
        'App\Models\PrivateVanTour'
    ];

    const VALID_SORTS = [
        'date', 'sender', 'receiver', 'amount', 'interact_bank', 'currency', 'created_at'
    ];

    /**
     * Get all cash images for BookingItemGroup with enhanced filtering
     */
    public function getList(Request $request)
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
                'message' => 'Cash images retrieved successfully',

            ];

        } catch (InvalidArgumentException $e) {
            Log::error($e);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Validation Error: ' . $e->getMessage(),
                'error_type' => 'validation'
            ];
        } catch (Exception $e) {
            Log::error($e);

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
                'interact_bank' => 'nullable|in:' . implode(',', self::VALID_INTERACT_BANK),
                'currency' => 'nullable|in:' . implode(',', self::VALID_CURRENCY),
                'product_type' => 'nullable|in:' . implode(',', self::VALID_PRODUCT_TYPES),
                'sort_by' => 'nullable|in:' . implode(',', self::VALID_SORTS),
                'sort_order' => 'nullable|in:asc,desc',
                'sender' => 'nullable|string|max:255',
                'receiver' => 'nullable|string|max:255',
                'amount' => 'nullable|numeric|min:0',
                'date' => 'nullable|string',
                'crm_id' => 'nullable|string|max:255',
                'product_id' => 'nullable|integer|min:1',
                'have_invoice' => 'nullable|boolean',
                'have_tax_receipt' => 'nullable',
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
     * Build optimized query for BookingItemGroup only
     */
    private function buildOptimizedQuery($filters)
    {
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
        ])
        ->where('relatable_type', 'App\Models\BookingItemGroup')
        ->where('relatable_id', '>', 0);

        // Apply date filter
        if (!empty($filters['date'])) {
            $this->applyDateFilter($query, $filters['date']);
        }

        // Apply search filters
        $this->applySearchFilters($query, $filters);

        // Apply sorting
        $this->applySorting($query, $filters);

        // Load relationships to match AccountanceCashImageResource expectations
        $query->with(['relatable']);

        // Load bookings only when relatable_id = 0 (many-to-many case) - not applicable for this service but kept for consistency
        $query->with(['bookings' => function($q) {
            $q->select('bookings.id', 'bookings.crm_id', 'bookings.invoice_number', 'bookings.grand_total', 'bookings.customer_id')
              ->with('customer:id,name');
        }]);

        return $query;
    }

    /**
     * Apply search filters
     */
    private function applySearchFilters($query, $filters)
    {
        // Basic cash image filters
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

        // CRM ID filter through BookingItemGroup -> Booking
        if (!empty($filters['crm_id'])) {
            $query->whereExists(function ($existsQuery) use ($filters) {
                $existsQuery->select(DB::raw(1))
                           ->from('booking_item_groups')
                           ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
                           ->whereColumn('booking_item_groups.id', 'cash_images.relatable_id')
                           ->where('bookings.crm_id', 'like', '%' . $filters['crm_id'] . '%');
            });
        }

        // Product ID filter through BookingItemGroup -> BookingItems
        if (!empty($filters['product_id'])) {
            $query->whereExists(function ($existsQuery) use ($filters) {
                $existsQuery->select(DB::raw(1))
                           ->from('booking_item_groups')
                           ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
                           ->whereColumn('booking_item_groups.id', 'cash_images.relatable_id')
                           ->where('booking_items.product_id', $filters['product_id']);
            });
        }

        // Product type filter through BookingItemGroup -> BookingItems
        if (!empty($filters['product_type'])) {
            $query->whereExists(function ($existsQuery) use ($filters) {
                $existsQuery->select(DB::raw(1))
                           ->from('booking_item_groups')
                           ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
                           ->whereColumn('booking_item_groups.id', 'cash_images.relatable_id')
                           ->where('booking_items.product_type', $filters['product_type']);
            });
        }

        // Invoice filter
        if (isset($filters['have_invoice'])) {
            if ($filters['have_invoice']) {
                // Has invoice
                $query->whereExists(function ($existsQuery) {
                    $existsQuery->select(DB::raw(1))
                               ->from('customer_documents')
                               ->whereColumn('customer_documents.booking_item_group_id', 'cash_images.relatable_id')
                               ->where('customer_documents.type', 'booking_confirm_letter');
                });
            } else {
                // No invoice
                $query->whereNotExists(function ($existsQuery) {
                    $existsQuery->select(DB::raw(1))
                               ->from('customer_documents')
                               ->whereColumn('customer_documents.booking_item_group_id', 'cash_images.relatable_id')
                               ->where('customer_documents.type', 'booking_confirm_letter');
                });
            }
        }

        // Tax receipt filter
        if (isset($filters['have_tax_receipt'])) {
            if ($filters['have_tax_receipt'] == 'have') {
                // Has tax receipt
                $query->whereExists(function ($existsQuery) {
                    $existsQuery->select(DB::raw(1))
                               ->from('tax_receipt_groups')
                               ->whereColumn('tax_receipt_groups.booking_item_group_id', 'cash_images.relatable_id');
                });
            } else {
                // No tax receipt
                $query->whereNotExists(function ($existsQuery) {
                    $existsQuery->select(DB::raw(1))
                               ->from('tax_receipt_groups')
                               ->whereColumn('tax_receipt_groups.booking_item_group_id', 'cash_images.relatable_id');
                });
            }
        }
    }

    /**
     * Apply sorting logic
     */
    private function applySorting($query, $filters)
    {
        if (!empty($filters['sort_by']) && !empty($filters['sort_order'])) {
            $sortBy = $filters['sort_by'];
            $sortOrder = strtolower($filters['sort_order']);

            $allowedSortFields = ['sender', 'receiver', 'amount', 'date', 'interact_bank', 'currency', 'created_at'];
            if (!in_array($sortBy, $allowedSortFields)) {
                return;
            }

            if (in_array($sortBy, ['sender', 'receiver'])) {
                if ($sortOrder === 'asc') {
                    $query->orderByRaw("LOWER($sortBy) ASC");
                } elseif ($sortOrder === 'desc') {
                    $query->orderByRaw("LOWER($sortBy) DESC");
                }
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            // Default sorting
            $query->orderBy('date', 'desc')->orderBy('created_at', 'desc');
        }
    }

    /**
     * Apply date filter
     */
    private function applyDateFilter($query, $dateFilter)
    {
        $dates = array_map('trim', explode(',', $dateFilter));

        if (count($dates) === 2) {
            $startDate = $dates[0];
            $endDate = $dates[1];
            $query->whereDate('date', '>=', $startDate)
                  ->whereDate('date', '<=', $endDate);
        } else {
            $singleDate = $dates[0];
            $query->whereDate('date', $singleDate);
        }
    }

    /**
     * Extract filters from request
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
            'crm_id' => $request->input('crm_id'),
            'product_id' => $request->input('product_id'),
            'product_type' => $request->input('product_type'),
            'have_invoice' => $request->has('have_invoice') ? $request->boolean('have_invoice') : null,
            'have_tax_receipt' => $request->has('have_tax_receipt') ? $request->input('have_tax_receipt') : null,
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ];
    }

    /**
     * Get available product types for BookingItemGroup cash images
     */
    public function getAvailableProductTypes()
    {
        try {
            $productTypes = DB::table('cash_images')
                ->join('booking_item_groups', 'cash_images.relatable_id', '=', 'booking_item_groups.id')
                ->join('booking_items', 'booking_item_groups.id', '=', 'booking_items.group_id')
                ->where('cash_images.relatable_type', 'App\Models\BookingItemGroup')
                ->where('cash_images.relatable_id', '>', 0)
                ->select('booking_items.product_type')
                ->distinct()
                ->pluck('product_type')
                ->filter()
                ->values()
                ->toArray();

            $validTypes = array_intersect($productTypes, self::VALID_PRODUCT_TYPES);

            return [
                'success' => true,
                'data' => array_values($validTypes),
                'message' => 'Available product types retrieved successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'An error occurred: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get summary statistics for partner cash images
     */
    public function getSummaryStatistics(Request $request)
    {
        try {
            $filters = $this->extractFilters($request);

            $query = CashImage::select([
                'cash_images.id',
                'cash_images.amount',
                'cash_images.currency',
                'cash_images.relatable_id'
            ])
            ->where('relatable_type', 'App\Models\BookingItemGroup')
            ->where('relatable_id', '>', 0);

            if (!empty($filters['date'])) {
                $this->applyDateFilter($query, $filters['date']);
            }
            $this->applySearchFilters($query, $filters);

            $cashImages = $query->get();

            // Invoice statistics
            $totalWithInvoice = CashImage::where('relatable_type', 'App\Models\BookingItemGroup')
                ->where('relatable_id', '>', 0)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('customer_documents')
                      ->whereColumn('customer_documents.booking_item_group_id', 'cash_images.relatable_id')
                      ->where('customer_documents.type', 'booking_confirm_letter');
                })->count();

            // Tax receipt statistics
            $totalWithTaxReceipt = CashImage::where('relatable_type', 'App\Models\BookingItemGroup')
                ->where('relatable_id', '>', 0)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('tax_receipt_groups')
                      ->whereColumn('tax_receipt_groups.booking_item_group_id', 'cash_images.relatable_id');
                })->count();

            $statistics = [
                'total_records' => $cashImages->count(),
                'total_amount_by_currency' => [],
                'invoice_statistics' => [
                    'with_invoice' => $totalWithInvoice,
                    'without_invoice' => $cashImages->count() - $totalWithInvoice
                ],
                'tax_receipt_statistics' => [
                    'with_tax_receipt' => $totalWithTaxReceipt,
                    'without_tax_receipt' => $cashImages->count() - $totalWithTaxReceipt
                ],
                'average_transaction_amount' => 0
            ];

            $currencyGroups = $cashImages->groupBy('currency');
            foreach ($currencyGroups as $currency => $items) {
                $statistics['total_amount_by_currency'][$currency] = $items->sum('amount');
            }

            if ($cashImages->count() > 0) {
                $statistics['average_transaction_amount'] = $cashImages->avg('amount');
            }

            return [
                'success' => true,
                'data' => $statistics,
                'message' => 'Summary statistics retrieved successfully'
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
