<?php

namespace App\Services;

use App\Http\Resources\Accountance\CashImageListResource as AccountanceCashImageResource;
use App\Models\CashImage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CashImageService
{
    const PER_PAGE = 10;
    const MAX_PER_PAGE = 100;

    const VALID_INTERACT_BANK = [
        'personal', 'company', 'all', 'cash_at_office', 'to_money_changer', 'deposit_management', 'pay_to_driver'
    ];
    const VALID_CURRENCY = [
        'MMK', 'THB', 'USD'
    ];

    // Add valid polymorphic types
    const VALID_RELATABLE_TYPES = [
        'App\Models\Booking',
        'App\Models\BookingItemGroup',
        'App\Models\CashBook',
        // Add other model types as needed
    ];

    const VALID_TAX_RECEIPT_FILTERS = [
        'have', 'missing', 'all'
    ];

    const VALID_SORTS = [
        'date', 'sender', 'receiver', 'amount', 'interact_bank', 'currency', 'created_at'
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
     * Validate request parameters (Added relatable_type validation)
     */
    private function validateRequest(Request $request)
    {
        $validator = Validator(
            $request->all(),
            [
                'interact_bank' => 'nullable|in:' . implode(',', self::VALID_INTERACT_BANK),
                'currency' => 'nullable|in:' . implode(',', self::VALID_CURRENCY),
                'relatable_type' => 'nullable|in:' . implode(',', self::VALID_RELATABLE_TYPES),
                'tax_receipts' => 'nullable|in:' . implode(',', self::VALID_TAX_RECEIPT_FILTERS),
                'sort_by' => 'nullable|in:' . implode(',', self::VALID_SORTS),
                'sort_order' => 'nullable|in:asc,desc',
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
     * Build optimized query with relatable_type filtering
     */
    private function buildOptimizedQuery($filters)
    {
        // Select only necessary columns for better performance
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
        ]);

        // Apply date filter
        if (!empty($filters['date'])) {
            $this->applyDateFilter($query, $filters['date']);
        }

        // Apply search filters (including relatable_type)
        $this->applySearchFilters($query, $filters);

        // Apply sorting - MOVED FROM applySearchFilters to avoid conflicts
        $this->applySorting($query, $filters);

        return $query;
    }

    /**
     * Apply sorting logic separately to avoid conflicts
     */
    private function applySorting($query, $filters)
    {
        if (!empty($filters['sort_by']) && !empty($filters['sort_order'])) {
            $sortBy = $filters['sort_by'];
            $sortOrder = strtolower($filters['sort_order']); // normalize to lowercase

            // Validate allowed sort fields
            $allowedSortFields = ['sender', 'receiver', 'amount', 'date'];
            if (!in_array($sortBy, $allowedSortFields)) {
                return; // or throw an exception if you prefer
            }

            // For string fields (sender, receiver), ensure case-insensitive sorting
            if (in_array($sortBy, ['sender', 'receiver'])) {
                if ($sortOrder === 'asc') {
                    $query->orderByRaw("LOWER($sortBy) ASC");
                } elseif ($sortOrder === 'desc') {
                    $query->orderByRaw("LOWER($sortBy) DESC");
                }
            }
            // For numeric (amount) or date fields, use regular orderBy
            else {
                $query->orderBy($sortBy, $sortOrder);
            }
        }
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
     * Apply search filters (REMOVED sort_by logic from here)
     */
    private function applySearchFilters($query, $filters)
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

        // Add relatable_type filter
        if (!empty($filters['relatable_type'])) {
            $query->where('relatable_type', $filters['relatable_type']);
        }

        // Apply tax receipts filter (only for BookingItemGroup)
        if (!empty($filters['tax_receipts']) && $filters['tax_receipts'] !== 'all') {
            $this->applyTaxReceiptsFilter($query, $filters['tax_receipts']);
        }

        // CRM ID filter (optimized for specific relatable_type)
        if (!empty($filters['crm_id'])) {
            $this->applyCrmIdFilter($query, $filters['crm_id'], $filters['relatable_type'] ?? null);
        }
    }

    /**
     * Apply tax receipts filter (only for BookingItemGroup)
     */
    private function applyTaxReceiptsFilter($query, $taxReceiptsFilter)
    {
        // Ensure we only apply to BookingItemGroup records
        $query->where('relatable_type', 'App\Models\BookingItemGroup');

        switch ($taxReceiptsFilter) {
            case 'have':
                // Show only BookingItemGroups with tax receipts
                $query->whereHas('taxReceipts', function ($q) {
                    $q->whereNotNull('tax_receipts.id');
                });

                break;

            case 'missing':
                // Show only BookingItemGroups without tax receipts
                $query->whereDoesntHave('taxReceipts');

                break;

            case 'all':
                // No additional filtering needed
                break;

            default:
                // Optionally handle invalid filter values
                break;
        }
    }

    /**
     * Apply CRM ID filter (optimized for specific relatable_type)
     */
    /**
     * Apply CRM ID filter (FIXED to support BookingItemGroup)
     */
    private function applyCrmIdFilter($query, $crmId, $relatableType = null)
    {
        // If relatable_type is specified, we can optimize the query
        if ($relatableType === 'App\Models\Booking') {
            $query->where('relatable_type', 'App\Models\Booking')
                ->whereExists(function ($existsQuery) use ($crmId) {
                    $existsQuery->select(DB::raw(1))
                        ->from('bookings')
                        ->whereColumn('bookings.id', 'cash_images.relatable_id')
                        ->where('bookings.crm_id', 'like', '%' . $crmId . '%');
                });
        } elseif ($relatableType === 'App\Models\BookingItemGroup') {
            // ADD THIS: Handle BookingItemGroup CRM filtering
            $query->where('relatable_type', 'App\Models\BookingItemGroup')
                ->whereExists(function ($existsQuery) use ($crmId) {
                    $existsQuery->select(DB::raw(1))
                        ->from('booking_item_groups')->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
                        ->whereColumn('booking_item_groups.id', 'cash_images.relatable_id')
                        ->where('bookings.crm_id', 'like', '%' . $crmId . '%');
                });
        } else {
            // General case - check all possible relatable types that might have crm_id
            $query->where(function ($q) use ($crmId) {
                // Booking
                $q->where(function ($bookingQuery) use ($crmId) {
                    $bookingQuery->where('relatable_type', 'App\Models\Booking')
                        ->whereExists(function ($existsQuery) use ($crmId) {
                            $existsQuery->select(DB::raw(1))
                                ->from('bookings')
                                ->whereColumn('bookings.id', 'cash_images.relatable_id')
                                ->where('bookings.crm_id', 'like', '%' . $crmId . '%');
                        });
                });

                // ADD THIS: BookingItemGroup
                $q->orWhere(function ($bookingItemGroupQuery) use ($crmId) {
                    $bookingItemGroupQuery->where('relatable_type', 'App\Models\BookingItemGroup')
                        ->whereExists(function ($existsQuery) use ($crmId) {
                            $existsQuery->select(DB::raw(1))
                                ->from('booking_item_groups')->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
                                ->whereColumn('booking_item_groups.id', 'cash_images.relatable_id')
                                ->where('bookings.crm_id', 'like', '%' . $crmId . '%');
                        });
                });

                // Add other model types that have crm_id if needed
            });
        }
    }

    /**
     * Extract filters from request (UPDATED to include sort parameters)
     */
    private function extractFilters(Request $request)
    {
        return [
            'interact_bank' => $request->input('interact_bank'),
            'currency' => $request->input('currency'),
            'relatable_type' => $request->input('relatable_type'),
            'receiver' => $request->input('receiver'),
            'sender' => $request->input('sender'),
            'amount' => $request->input('amount'),
            'date' => $request->input('date'),
            'crm_id' => $request->input('crm_id'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order')
        ];
    }

    /**
     * Alternative: Get basic list with relatable_type filtering (ALSO FIXED)
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
            ]);

            // Only apply date filter if provided (most common filter)
            if ($request->filled('date')) {
                $query->whereDate('date', $request->input('date'));
            }

            // Apply relatable_type filter if provided
            if ($request->filled('relatable_type')) {
                $relatableType = $request->input('relatable_type');
                if (in_array($relatableType, self::VALID_RELATABLE_TYPES)) {
                    $query->where('relatable_type', $relatableType);
                }
            }

            // Apply tax receipts filter
            if ($request->filled('tax_receipts')) {
                $taxReceiptsFilter = $request->input('tax_receipts');
                if (in_array($taxReceiptsFilter, self::VALID_TAX_RECEIPT_FILTERS) && $taxReceiptsFilter !== 'all') {
                    $this->applyTaxReceiptsFilter($query, $taxReceiptsFilter);
                }
            }

            // FIXED: Apply sorting logic properly
            if ($request->filled('sort_by') && in_array($request->input('sort_by'), self::VALID_SORTS)) {
                $sortOrder = $request->input('sort_order', 'desc');
                if (!in_array($sortOrder, ['asc', 'desc'])) {
                    $sortOrder = 'desc'; // Default to desc if invalid
                }
                $query->orderBy($request->input('sort_by'), $sortOrder);
            } else {
                // Default sorting when no custom sort is specified
                $query->orderBy('date', 'desc')->orderBy('created_at', 'desc');
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

    // ... rest of the methods remain the same ...

    /**
     * Get available relatable types for filtering
     */
    public function getAvailableRelatableTypes()
    {
        try {
            $types = CashImage::select('relatable_type')
                ->distinct()
                ->whereNotNull('relatable_type')
                ->pluck('relatable_type')
                ->toArray();

            // Filter only valid types
            $validTypes = array_intersect($types, self::VALID_RELATABLE_TYPES);

            return [
                'success' => true,
                'data' => array_values($validTypes),
                'message' => 'Available relatable types retrieved successfully'
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
     * Get cash images count by relatable type
     */
    public function getCountByRelatableType()
    {
        try {
            $counts = CashImage::select('relatable_type', DB::raw('COUNT(*) as count'))
                ->whereNotNull('relatable_type')
                ->whereIn('relatable_type', self::VALID_RELATABLE_TYPES)
                ->groupBy('relatable_type')
                ->get()
                ->pluck('count', 'relatable_type')
                ->toArray();

            return [
                'success' => true,
                'data' => $counts,
                'message' => 'Counts by relatable type retrieved successfully'
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
     * Get all cash images summary with related booking data
     */
    public function getAllSummary(Request $request)
    {
        try {
            $this->validateRequest($request);

            $limit = min((int) $request->get('limit', self::PER_PAGE), self::MAX_PER_PAGE);
            $filters = $this->extractFilters($request);

            $query = $this->buildSummaryQuery($filters);
            $data = $query->paginate($limit);

            // Transform the data to include summary information
            $transformedData = $data->getCollection()->map(function ($cashImage) {
                return $this->transformCashImageToSummary($cashImage);
            });

            // Replace the collection with transformed data
            $data->setCollection($transformedData);

            return [
                'status' => 1,
                'message' => 'Cash images summary retrieved successfully',
                'result' => [
                    'data' => $transformedData,
                    'links' => [
                        'first' => $data->url(1),
                        'last' => $data->url($data->lastPage()),
                        'prev' => $data->previousPageUrl(),
                        'next' => $data->nextPageUrl(),
                    ],
                    'meta' => [
                        'current_page' => $data->currentPage(),
                        'from' => $data->firstItem(),
                        'last_page' => $data->lastPage(),
                        'links' => $data->linkCollection(),
                        'path' => $data->path(),
                        'per_page' => $data->perPage(),
                        'to' => $data->lastItem(),
                        'total' => $data->total(),
                    ]
                ]
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'status' => 'Error has occurred.',
                'message' => 'Validation Error: ' . $e->getMessage(),
                'result' => null
            ];
        } catch (Exception $e) {
            return [
                'status' => 'Error has occurred.',
                'message' => 'An error occurred while retrieving cash images summary. Error: ' . $e->getMessage(),
                'result' => null
            ];
        }
    }

    /**
     * Build optimized query for summary with eager loading (UPDATED with proper sorting)
     */
    private function buildSummaryQuery($filters)
    {
        $query = CashImage::select([
            'cash_images.id',
            'cash_images.date',
            'cash_images.sender',
            'cash_images.receiver',
            'cash_images.amount',
            'cash_images.interact_bank',
            'cash_images.currency',
            'cash_images.created_at',
            'cash_images.relatable_id',
            'cash_images.relatable_type'
        ]);

        // Apply date filter
        if (!empty($filters['date'])) {
            $this->applyDateFilter($query, $filters['date']);
        }

        // Apply search filters
        $this->applySearchFilters($query, $filters);

        // Apply sorting
        $this->applySorting($query, $filters);

        // Conditionally eager load based on relatable_type
        $query->with(['relatable' => function ($morphQuery) {
            // This will only load the polymorphic relationship
            // Additional loading will be handled in the transform method
        }]);

        // If we're filtering by booking type, we can optimize further
        if (!empty($filters['relatable_type']) && $filters['relatable_type'] === 'App\Models\Booking') {
            $query->with([
                'relatable.items.product',
                'relatable.customer'
            ]);
        }

        return $query;
    }

    /**
     * Transform cash image data to summary format
     */
    private function transformCashImageToSummary($cashImage)
    {
        $summary = [
            'cash_image_id' => $cashImage->id,
            'cash_image_date' => $cashImage->date,
            'bank' => $cashImage->interact_bank,
            'currency' => $cashImage->currency,
            'cash_amount' => $cashImage->amount,
            'invoice_id' => null,
            'crm_id' => null,
            'customer_name' => null,
            'hotel_service_total' => 0,
            'hotel_service_cost' => 0,
            'hotel_service_vat' => 0,
            'hotel_service_commission' => 0,
            'ticket_service_total' => 0,
            'ticket_service_cost' => 0,
            'ticket_service_vat' => 0,
            'ticket_service_commission' => 0,
            'total_sales' => 0,
            'total_price' => 0,
            'total_before_vat' => 0,
            'vat' => 0,
            'commission' => 0,
        ];

        // Process only if related to booking
        if ($cashImage->relatable_type === 'App\Models\Booking' && $cashImage->relatable) {
            $booking = $cashImage->relatable;

            $summary['invoice_id'] = $booking->id ?? null;
            $summary['crm_id'] = $booking->crm_id ?? null;

            // Load customer if not already loaded
            if (!$booking->relationLoaded('customer') && $booking->customer_id) {
                $booking->load('customer');
            }
            $summary['customer_name'] = optional($booking->customer)->name;

            // Load booking items if not already loaded
            if (!$booking->relationLoaded('items')) {
                $booking->load('items.product');
            }

            // Calculate service totals from booking items
            $hotelTotal = 0;
            $hotelCost = 0;
            $ticketTotal = 0;
            $ticketCost = 0;
            $hotelVat = 0;
            $hotelCommission = 0;
            $ticketVat = 0;
            $ticketCommission = 0;

            if ($booking->items && $booking->items->count() > 0) {
                foreach ($booking->items as $item) {
                    $productType = $item->product_type ?? null;
                    $amount = $item->amount ?? 0;
                    $cost = $item->total_cost_price ?? 0;
                    $vat = $item->output_vat ?? 0;
                    $commission = $item->commission ?? 0;

                    if ($productType === 'App\\Models\\Hotel') {
                        $hotelTotal += $amount;
                        $hotelCost += $cost;
                        $hotelVat += $vat;
                        $hotelCommission += $commission;
                    } elseif ($productType === 'App\\Models\\EntranceTicket') {
                        $ticketTotal += $amount;
                        $ticketCost += $cost;
                        $ticketVat += $vat;
                        $ticketCommission += $commission;
                    }
                }
            }

            $summary['hotel_service_total'] = $hotelTotal;
            $summary['hotel_service_cost'] = $hotelCost;
            $summary['hotel_service_vat'] = $hotelVat;
            $summary['hotel_service_commission'] = $hotelCommission;
            $summary['ticket_service_total'] = $ticketTotal;
            $summary['ticket_service_cost'] = $ticketCost;
            $summary['ticket_service_vat'] = $ticketVat;
            $summary['ticket_service_commission'] = $ticketCommission;
            $summary['total_price'] = $booking->total_price ?? ($hotelTotal + $ticketTotal);
            $summary['total_sales'] = $booking->grand_total ?? 0;
            $summary['total_before_vat'] = $booking->total_before_vat ?? 0;
            $summary['vat'] = $booking->output_vat ?? 0;
            $summary['commission'] = $booking->commission ?? 0;
        }

        return $summary;
    }

    /**
     * Get summary statistics
     */
    public function getSummaryStatistics(Request $request)
    {
        try {
            $filters = $this->extractFilters($request);

            // Build base query for statistics
            $query = CashImage::select([
                'cash_images.id',
                'cash_images.amount',
                'cash_images.currency',
                'cash_images.relatable_id',
                'cash_images.relatable_type'
            ]);

            // Apply filters
            if (!empty($filters['date'])) {
                $this->applyDateFilter($query, $filters['date']);
            }
            $this->applySearchFilters($query, $filters);

            $cashImages = $query->get();

            $statistics = [
                'total_cash_records' => $cashImages->count(),
                'total_cash_amount_by_currency' => [],
                'booking_related_count' => 0,
                'total_hotel_services' => 0,
                'total_ticket_services' => 0,
                'average_transaction_amount' => 0
            ];

            // Group by currency
            $currencyGroups = $cashImages->groupBy('currency');
            foreach ($currencyGroups as $currency => $items) {
                $statistics['total_cash_amount_by_currency'][$currency] = $items->sum('amount');
            }

            // Count booking related records
            $bookingRelated = $cashImages->where('relatable_type', 'App\Models\Booking');
            $statistics['booking_related_count'] = $bookingRelated->count();

            // Calculate average
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
