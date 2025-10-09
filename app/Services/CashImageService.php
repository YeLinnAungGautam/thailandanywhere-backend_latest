<?php

namespace App\Services;

use App\Http\Resources\Accountance\CashImageDetailResource;
use App\Http\Resources\Accountance\CashImageListResource as AccountanceCashImageResource;
use App\Http\Resources\Accountance\CashParchaseDetailResource;
use App\Models\Booking;
use App\Models\CashImage;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Format;

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

    const VALID_RELATABLE_TYPES = [
        'App\Models\Booking',
        'App\Models\BookingItemGroup',
        'App\Models\CashBook',
    ];

    const VALID_TAX_RECEIPT_FILTERS = [
        'have', 'missing', 'all'
    ];

    const VALID_SORTS = [
        'date', 'sender', 'receiver', 'amount', 'interact_bank', 'currency', 'created_at'
    ];

    const VALID_TYPE_FILTERS = [
        'invoice_have', 'invoice_missing', 'tax_receipt_have', 'tax_receipt_missing'
    ];

    /**
     * Get all cash images with filtering and pagination (Simple update for relatable_id = 0)
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
     * Validate request parameters
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
                'type' => 'nullable|in:' . implode(',', self::VALID_TYPE_FILTERS),
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
     * Build optimized query (Simple load for relatable_id = 0)
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
            'data_verify',
            'internal_transfer',
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

        // Apply search filters
        $this->applySearchFilters($query, $filters);

        // Apply sorting
        $this->applySorting($query, $filters);

        // Load relationships conditionally
        $query->with(['relatable']);

        // Load bookings only when relatable_id = 0 (many-to-many case)
        $query->with(['bookings' => function($q) {
            $q->select('bookings.id', 'bookings.crm_id', 'bookings.invoice_number', 'bookings.grand_total', 'bookings.customer_id')
              ->with('customer:id,name');
        }]);

        return $query;
    }

    /**
     * Apply search filters (Updated CRM filter for many-to-many)
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

        if (!empty($filters['relatable_type'])) {
            $query->where('relatable_type', $filters['relatable_type']);
        }

        // Apply tax receipts filter
        if (!empty($filters['tax_receipts']) && $filters['tax_receipts'] !== 'all') {
            $this->applyTaxReceiptsFilter($query, $filters['tax_receipts']);
        }

        // CRM ID filter (Updated to handle both cases simply)
        if (!empty($filters['crm_id'])) {
            $this->applyCrmIdFilter($query, $filters['crm_id'], $filters['relatable_type'] ?? null);
        }

        // Apply filter type
        if (!empty($filters['filter_type'])) {
            $this->applyFilterType($query, $filters['filter_type']);
        }

        if (!empty($filters['filter_type_invoice'])) {
            $this->applyFilterTypeInvoice($query, $filters['filter_type_invoice']);
        }
    }

    private function applyFilterType($query, $type)
    {
        switch ($type) {
            case 'tax_receipt_have':
                $this->applyTaxReceiptFilter($query, true);
                break;

            case 'tax_receipt_missing':
                $this->applyTaxReceiptFilter($query, false);
                break;

            default:
                // No filter applied for unknown types
                break;
        }
    }

     private function applyFilterTypeInvoice($query, $type)
    {
        switch ($type) {
            case 'invoice_have':
                $this->applyInvoiceFilter($query, true);
                break;

            case 'invoice_missing':
                $this->applyInvoiceFilter($query, false);
                break;

            default:
                // No filter applied for unknown types
                break;
        }
    }

    private function applyInvoiceFilter($query, $hasInvoice = true)
    {
        // Only apply to BookingItemGroup relatable type
        $query->where('relatable_type', 'App\Models\BookingItemGroup');

        if ($hasInvoice) {
            // Filter for cash images that have invoices
            $query->whereExists(function ($existsQuery) {
                $existsQuery->select(DB::raw(1))
                           ->from('customer_documents')
                           ->whereColumn('customer_documents.booking_item_group_id', 'cash_images.relatable_id')
                           ->where('customer_documents.type', 'booking_confirm_letter');
            });
        } else {
            // Filter for cash images that don't have invoices
            $query->whereNotExists(function ($existsQuery) {
                $existsQuery->select(DB::raw(1))
                           ->from('customer_documents')
                           ->whereColumn('customer_documents.booking_item_group_id', 'cash_images.relatable_id')
                           ->where('customer_documents.type', 'booking_confirm_letter');
            });
        }
    }

    /**
     * Apply tax receipt filter for BookingItemGroup
     */
    private function applyTaxReceiptFilter($query, $hasTaxReceipt = true)
    {
        // Only apply to BookingItemGroup relatable type
        $query->where('relatable_type', 'App\Models\BookingItemGroup');

        if ($hasTaxReceipt) {
            // Filter for cash images that have tax receipts
            $query->whereExists(function ($existsQuery) {
                $existsQuery->select(DB::raw(1))
                           ->from('tax_receipt_groups')
                           ->whereColumn('tax_receipt_groups.booking_item_group_id', 'cash_images.relatable_id');
            });
        } else {
            // Filter for cash images that don't have tax receipts
            $query->whereNotExists(function ($existsQuery) {
                $existsQuery->select(DB::raw(1))
                           ->from('tax_receipt_groups')
                           ->whereColumn('tax_receipt_groups.booking_item_group_id', 'cash_images.relatable_id');
            });
        }
    }

    /**
     * Apply CRM ID filter (Simple version for both polymorphic and many-to-many)
     */
    private function applyCrmIdFilter($query, $crmId, $relatableType = null)
    {
        if ($relatableType === 'App\Models\Booking') {
            $query->where(function ($mainQuery) use ($crmId) {
                // Case 1: Direct polymorphic relationship (relatable_id > 0)
                $mainQuery->where(function ($polyQuery) use ($crmId) {
                    $polyQuery->where('relatable_type', 'App\Models\Booking')
                             ->where('relatable_id', '>', 0)
                             ->whereExists(function ($existsQuery) use ($crmId) {
                                 $existsQuery->select(DB::raw(1))
                                            ->from('bookings')
                                            ->whereColumn('bookings.id', 'cash_images.relatable_id')
                                            ->where('bookings.crm_id', 'like', '%' . $crmId . '%');
                             });
                });

                // Case 2: Many-to-many relationship (relatable_id = 0)
                $mainQuery->orWhere(function ($manyQuery) use ($crmId) {
                    $manyQuery->where('relatable_type', 'App\Models\Booking')
                             ->where('relatable_id', 0)
                             ->whereExists(function ($existsQuery) use ($crmId) {
                                 $existsQuery->select(DB::raw(1))
                                            ->from('cash_image_bookings')
                                            ->join('bookings', 'cash_image_bookings.booking_id', '=', 'bookings.id')
                                            ->whereColumn('cash_image_bookings.cash_image_id', 'cash_images.id')
                                            ->where('bookings.crm_id', 'like', '%' . $crmId . '%');
                             });
                });
            });
        } elseif ($relatableType === 'App\Models\BookingItemGroup') {
            $query->where('relatable_type', 'App\Models\BookingItemGroup')
                  ->whereExists(function ($existsQuery) use ($crmId) {
                      $existsQuery->select(DB::raw(1))
                                 ->from('booking_item_groups')
                                 ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
                                 ->whereColumn('booking_item_groups.id', 'cash_images.relatable_id')
                                 ->where('bookings.crm_id', 'like', '%' . $crmId . '%');
                  });
        } else {
            // General case - check all possible types
            $query->where(function ($q) use ($crmId) {
                // Direct Booking polymorphic
                $q->where(function ($bookingQuery) use ($crmId) {
                    $bookingQuery->where('relatable_type', 'App\Models\Booking')
                                ->where('relatable_id', '>', 0)
                                ->whereExists(function ($existsQuery) use ($crmId) {
                                    $existsQuery->select(DB::raw(1))
                                               ->from('bookings')
                                               ->whereColumn('bookings.id', 'cash_images.relatable_id')
                                               ->where('bookings.crm_id', 'like', '%' . $crmId . '%');
                                });
                });

                // Many-to-many Booking
                $q->orWhere(function ($manyQuery) use ($crmId) {
                    $manyQuery->where('relatable_type', 'App\Models\Booking')
                             ->where('relatable_id', 0)
                             ->whereExists(function ($existsQuery) use ($crmId) {
                                 $existsQuery->select(DB::raw(1))
                                            ->from('cash_image_bookings')
                                            ->join('bookings', 'cash_image_bookings.booking_id', '=', 'bookings.id')
                                            ->whereColumn('cash_image_bookings.cash_image_id', 'cash_images.id')
                                            ->where('bookings.crm_id', 'like', '%' . $crmId . '%');
                             });
                });

                // BookingItemGroup
                $q->orWhere(function ($itemGroupQuery) use ($crmId) {
                    $itemGroupQuery->where('relatable_type', 'App\Models\BookingItemGroup')
                                  ->whereExists(function ($existsQuery) use ($crmId) {
                                      $existsQuery->select(DB::raw(1))
                                                 ->from('booking_item_groups')
                                                 ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
                                                 ->whereColumn('booking_item_groups.id', 'cash_images.relatable_id')
                                                 ->where('bookings.crm_id', 'like', '%' . $crmId . '%');
                                  });
                });
            });
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

            $allowedSortFields = ['sender', 'receiver', 'amount', 'date'];
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
     * Apply tax receipts filter
     */
    private function applyTaxReceiptsFilter($query, $taxReceiptsFilter)
    {
        $query->where('relatable_type', 'App\Models\BookingItemGroup');

        switch ($taxReceiptsFilter) {
            case 'have':
                $query->whereHas('taxReceipts', function ($q) {
                    $q->whereNotNull('tax_receipts.id');
                });
                break;

            case 'missing':
                $query->whereDoesntHave('taxReceipts');
                break;

            case 'all':
                break;
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
            'relatable_type' => $request->input('relatable_type'),
            'receiver' => $request->input('receiver'),
            'sender' => $request->input('sender'),
            'amount' => $request->input('amount'),
            'date' => $request->input('date'),
            'crm_id' => $request->input('crm_id'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
            'filter_type' => $request->input('filter_type'),
            'filter_type_invoice' => $request->input('filter_type_invoice'),
        ];
    }



    /**
     * Get basic list (Updated for simple many-to-many handling)
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

            if ($request->filled('date')) {
                $query->whereDate('date', $request->input('date'));
            }

            if ($request->filled('relatable_type')) {
                $relatableType = $request->input('relatable_type');
                if (in_array($relatableType, self::VALID_RELATABLE_TYPES)) {
                    $query->where('relatable_type', $relatableType);
                }
            }

            if ($request->filled('tax_receipts')) {
                $taxReceiptsFilter = $request->input('tax_receipts');
                if (in_array($taxReceiptsFilter, self::VALID_TAX_RECEIPT_FILTERS) && $taxReceiptsFilter !== 'all') {
                    $this->applyTaxReceiptsFilter($query, $taxReceiptsFilter);
                }
            }

            if ($request->filled('sort_by') && in_array($request->input('sort_by'), self::VALID_SORTS)) {
                $sortOrder = $request->input('sort_order', 'desc');
                if (!in_array($sortOrder, ['asc', 'desc'])) {
                    $sortOrder = 'desc';
                }
                $query->orderBy($request->input('sort_by'), $sortOrder);
            } else {
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

    // Add property to store CRM deposit counters
    private $crmDepositCounters = [];

    /**
     * Get all cash images summary (Simple version for many-to-many)
     */
    public function getAllSummary(Request $request)
    {
        try {
            $this->validateRequest($request);

            $limit = min((int) $request->get('limit', self::PER_PAGE), self::MAX_PER_PAGE);
            $filters = $this->extractFilters($request);

            // Reset deposit counters for each request
            $this->crmDepositCounters = [];

            // IMPORTANT: Pre-calculate deposit numbers for ALL records (not just current page)
            // This ensures correct deposit numbering even when sorting/filtering
            $this->preCalculateAllDepositNumbers($filters);

            $query = $this->buildSummaryQuery($filters);
            $data = $query->paginate($limit);

            // Transform the data to include summary information
            $transformedData = $data->getCollection()->map(function ($cashImage) {
                return $this->transformCashImageToSummary($cashImage);
            });

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

    public function getAllSummaryForExport(Request $request)
    {
        try {
            $this->validateRequest($request);
            $filters = $this->extractFilters($request);

            // Reset deposit counters for each request
            $this->crmDepositCounters = [];

            // Pre-calculate deposit numbers for ALL records
            $this->preCalculateAllDepositNumbers($filters);

            // Build query without pagination
            $query = $this->buildSummaryQuery($filters);
            $allData = $query->get(); // Get ALL records, no pagination

            // Transform the data to include summary information
            $transformedData = $allData->map(function ($cashImage) {
                return $this->transformCashImageToSummary($cashImage);
            });

            return [
                'status' => 1,
                'message' => 'All cash images summary retrieved successfully',
                'result' => [
                    'data' => $transformedData,
                    'total_records' => $transformedData->count(),
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

    public function getAllParchaseForExport(Request $request)
    {
        try {
            $this->validateRequest($request);

            $filters = $this->extractFilters($request);

            $query = $this->buildOptimizedQuery($filters);
            $data = $query->get();

            $resourceCollection = AccountanceCashImageResource::collection($data);

            return [
                'status' => 1,
                'message' => 'All cash images summary retrieved successfully',
                'result' => [
                    'data' => $resourceCollection->response()->getData(true),
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

    public function getAllParchaseLimitForExport(Request $request)
    {
        try {
            $limit = $request->get('limit', 50);
            $offset = $request->get('offset', 0);
            $this->validateRequest($request);

            $filters = $this->extractFilters($request);

            $query = $this->buildOptimizedQuery($filters);
            $data = $query->skip($offset)->take($limit)->get();

            $resourceCollection = AccountanceCashImageResource::collection($data);

            return [
                'status' => 1,
                'message' => 'All cash images summary retrieved successfully',
                'result' => [
                    'data' => $resourceCollection->response()->getData(true),
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

    public function getAllGroupedByProductForExport(Request $request)
    {
        try {
            $this->validateRequest($request);
            $filters = $this->extractFilters($request);

            // Get pagination parameters
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 100);

            // Build optimized query specifically for BookingItemGroup - GET ALL DATA FIRST
            $query = CashImage::select([
                'cash_images.id',
                'cash_images.date',
                'cash_images.sender',
                'cash_images.receiver',
                'cash_images.amount',
                'cash_images.interact_bank',
                'cash_images.currency',
                'cash_images.image',
                'cash_images.relatable_id',
                'cash_images.relatable_type',
                'cash_images.created_at'
            ])
            ->where('relatable_type', 'App\Models\BookingItemGroup')
            ->where('relatable_id', '>', 0); // Only valid BookingItemGroup relationships

            // Apply filters
            if (!empty($filters['date'])) {
                $this->applyDateFilter($query, $filters['date']);
            }
            $this->applySearchFilters($query, $filters);

            // Load BookingItemGroup with its booking and items
            $query->with([
                'relatable' => function($q) {
                    $q->select(['id', 'booking_id'])
                      ->with([
                          'booking' => function($bookingQuery) {
                              $bookingQuery->select(['id', 'crm_id', 'customer_id'])
                                          ->with(['items.product', 'customer']);
                          }
                      ]);
                }
            ]);

            // GET ALL DATA (no pagination yet)
            $allData = $query->get();

            // Batch get tax receipt counts for performance
            $bookingItemGroupIds = $allData->pluck('relatable_id')->filter()->toArray();
            $taxReceiptCounts = $this->getBatchTaxReceiptCounts($bookingItemGroupIds);

            // Group by product FIRST
            $groupedByProduct = [];

            foreach ($allData as $cashImage) {
                $products = $this->extractProductsFromBookingItemGroup($cashImage);
                $taxReceiptStatus = $this->getSimpleTaxReceiptStatus($cashImage, $taxReceiptCounts);

                foreach ($products as $productInfo) {
                    $productName = $productInfo['product_name'];

                    if (!isset($groupedByProduct[$productName])) {
                        $groupedByProduct[$productName] = [
                            'product_name' => $productName,
                            'product_type' => $productInfo['product_type'],
                            'total_records' => 0,
                            'tax_receipt_summary' => [
                                'have_tax_receipt' => 0,
                                'missing_tax_receipt' => 0,
                                'not_applicable' => 0
                            ],
                            'cash_images' => []
                        ];
                    }

                    $groupedByProduct[$productName]['total_records']++;

                    // Update tax receipt counters
                    switch ($taxReceiptStatus) {
                        case 'have':
                            $groupedByProduct[$productName]['tax_receipt_summary']['have_tax_receipt']++;
                            break;
                        case 'missing':
                            $groupedByProduct[$productName]['tax_receipt_summary']['missing_tax_receipt']++;
                            break;
                        case 'not_applicable':
                            $groupedByProduct[$productName]['tax_receipt_summary']['not_applicable']++;
                            break;
                    }

                    // Create simplified cash image data
                    $cashImageData = [
                        'id' => $cashImage->id,
                        'date' => $cashImage->date ? $cashImage->date->format('d-m-Y H:i:s') : null,
                        'sender' => $cashImage->sender,
                        'receiver' => $cashImage->receiver,
                        'amount' => $cashImage->amount,
                        'interact_bank' => $cashImage->interact_bank,
                        'currency' => $cashImage->currency,
                        'image' => $cashImage->image,
                        'crm_id' => $cashImage->relatable && $cashImage->relatable->booking ? $cashImage->relatable->booking->crm_id : null,
                        'customer_name' => $cashImage->relatable && $cashImage->relatable->booking && $cashImage->relatable->booking->customer ? $cashImage->relatable->booking->customer->name : null,
                        'tax_receipt_status' => $taxReceiptStatus,
                    ];

                    $groupedByProduct[$productName]['cash_images'][] = $cashImageData;
                }
            }

            // Sort groups by total records (descending)
            uasort($groupedByProduct, function($a, $b) {
                return $b['total_records'] <=> $a['total_records'];
            });

            // Convert to indexed array for easier pagination
            $groupedArray = array_values($groupedByProduct);

            // Calculate total counts BEFORE pagination
            $totalGroups = count($groupedArray);
            $totalCashImages = $allData->count();

            // Apply pagination to GROUPS (not individual cash images)
            $offset = ($page - 1) * $limit;
            $paginatedGroups = array_slice($groupedArray, $offset, $limit);

            // Calculate pagination info for groups
            $totalPages = ceil($totalGroups / $limit);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;

            // Calculate overall summary from ALL data (not just current page)
            $overallTaxReceiptSummary = [
                'total_have_tax_receipt' => 0,
                'total_missing_tax_receipt' => 0,
                'total_not_applicable' => 0
            ];

            foreach ($groupedArray as $group) {
                $overallTaxReceiptSummary['total_have_tax_receipt'] += $group['tax_receipt_summary']['have_tax_receipt'];
                $overallTaxReceiptSummary['total_missing_tax_receipt'] += $group['tax_receipt_summary']['missing_tax_receipt'];
                $overallTaxReceiptSummary['total_not_applicable'] += $group['tax_receipt_summary']['not_applicable'];
            }

            // Calculate current page cash images count
            $currentPageCashImagesCount = 0;
            foreach ($paginatedGroups as $group) {
                $currentPageCashImagesCount += count($group['cash_images']);
            }

            return [
                'status' => 1,
                'message' => 'BookingItemGroup cash images grouped by product retrieved successfully',
                'result' => [
                    'summary' => [
                        'total_cash_images' => $totalCashImages,
                        'current_page_cash_images_count' => $currentPageCashImagesCount,
                        'total_products' => $totalGroups,
                        'current_page_products_count' => count($paginatedGroups),
                        'current_page' => $page,
                        'last_page' => $totalPages,
                        'per_page' => $limit,
                        'tax_receipt_summary' => $overallTaxReceiptSummary
                    ],
                    'grouped_data' => $paginatedGroups,
                    'pagination' => [
                        'current_page' => $page,
                        'last_page' => $totalPages,
                        'per_page' => $limit,
                        'total_groups' => $totalGroups,
                        'from_group' => $offset + 1,
                        'to_group' => min($offset + $limit, $totalGroups),
                        'has_next_page' => $hasNextPage,
                        'has_prev_page' => $hasPrevPage,
                        'next_page_url' => $hasNextPage ? request()->url() . '?' . http_build_query(array_merge(request()->all(), ['page' => $page + 1])) : null,
                        'prev_page_url' => $hasPrevPage ? request()->url() . '?' . http_build_query(array_merge(request()->all(), ['page' => $page - 1])) : null
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
                'message' => 'An error occurred while retrieving grouped cash images. Error: ' . $e->getMessage(),
                'result' => null
            ];
        }
    }

    /**
     * Extract products specifically from BookingItemGroup
     */
    private function extractProductsFromBookingItemGroup($cashImage)
    {
        $products = [];

        if (!$cashImage->relatable) {
            return [[
                'product_name' => 'Uncategorized',
                'product_type' => 'General'
            ]];
        }

        $bookingItemGroup = $cashImage->relatable;

        // Get products from bookingItems relationship
        if ($bookingItemGroup->bookingItems && $bookingItemGroup->bookingItems->count() > 0) {
            foreach ($bookingItemGroup->bookingItems as $bookingItem) {
                if ($bookingItem->product) {
                    $products[] = [
                        'product_name' => $bookingItem->product->name ?? 'Unknown Product',
                        'product_type' => $this->getProductTypeDisplayName($bookingItem->product_type)
                    ];
                } else if ($bookingItem->product_id) {
                    $products[] = [
                        'product_name' => 'Product ID: ' . $bookingItem->product_id,
                        'product_type' => $this->getProductTypeDisplayName($bookingItem->product_type ?? null)
                    ];
                }
            }
        }

        // Remove duplicates
        $products = array_unique($products, SORT_REGULAR);

        // If no products found, use default
        if (empty($products)) {
            $products[] = [
                'product_name' => 'Uncategorized',
                'product_type' => 'General'
            ];
        }

        return $products;
    }

    /**
     * Batch fetch tax receipt counts for BookingItemGroup IDs
     */
    private function getBatchTaxReceiptCounts($bookingItemGroupIds)
    {
        if (empty($bookingItemGroupIds)) {
            return [];
        }

        $taxReceiptIds = DB::table('tax_receipt_groups')
            ->whereIn('booking_item_group_id', $bookingItemGroupIds)
            ->pluck('booking_item_group_id')
            ->toArray();

        return array_flip($taxReceiptIds);
    }

    /**
     * Simple tax receipt status check using batch data
     */
    private function getSimpleTaxReceiptStatus($cashImage, $taxReceiptCounts)
    {
        if ($cashImage->relatable_type !== 'App\Models\BookingItemGroup' || !$cashImage->relatable_id) {
            return 'not_applicable';
        }

        return isset($taxReceiptCounts[$cashImage->relatable_id]) ? 'have' : 'missing';
    }

    /**
     * Get human-readable product type name
     */
    private function getProductTypeDisplayName($productType)
    {
        $typeMap = [
            'App\\Models\\Hotel' => 'Hotel Service',
            'App\\Models\\EntranceTicket' => 'Ticket Service',
            'App\\Models\\PrivateVanTour' => 'Car Rental Service',
        ];

        return $typeMap[$productType] ?? 'General Service';
    }

    /**
     * Enhanced validation for new parameters
     */


    /**
     * Batch အတွက် specific data ယူမယ် (offset နဲ့ limit နဲ့)
     */
    public function getAllPurchaseForPrintBatch(Request $request, int $offset, int $limit)
    {
        try {
            $this->validateRequest($request);
            $filters = $this->extractFilters($request);

            $query = $this->buildOptimizedQuery($filters);

            // Offset နဲ့ limit သုံးပြီး specific batch ယူမယ်
            $data = $query->offset($offset)
                         ->limit($limit)
                         ->get();

            $resourceCollection = CashParchaseDetailResource::collection($data);

            return [
                'result' => $resourceCollection->response()->getData(true),
                'batch_info' => [
                    'offset' => $offset,
                    'limit' => $limit,
                    'count' => $data->count(),
                    'from_record' => $offset + 1,
                    'to_record' => $offset + $data->count()
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
                'message' => 'An error occurred while retrieving batch data. Error: ' . $e->getMessage(),
                'result' => null
            ];
        }
    }

    public function onlyImages(Request $request) {
        try {
            $this->validateRequest($request);
            $filters = $this->extractFilters($request);

            // Build query with select for cash images
            $query = $this->buildSummaryQuery($filters);
            $allData = $query->select([
                'id',
                'image',
                'relatable_id',
                'relatable_type',
            ])->with([
                'relatable' => function($query) {
                    $query->select([
                        'id', 'crm_id', 'grand_total', 'customer_id', 'commission',
                        'created_at', 'start_date', 'end_date', 'booking_date',
                        'payment_method', 'payment_status', 'bank_name', 'discount', 'sub_total',

                    ]);
                },
                'relatable.customer' => function($query) {
                    $query->select(['id', 'name', 'phone_number']);
                },
                'relatable.items' => function($query) {
                    $query->select([
                        'id', 'booking_id', 'product_id', 'quantity', 'selling_price',
                        'total_cost_price', 'discount', 'product_type', 'amount',
                        'comment', 'service_date', 'days','checkin_date','checkout_date',
                    ]);
                },
                'relatable.items.product'
            ])->get();

            // Transform the data
            $transformedData = $allData->map(function ($cashImage) use (&$nextInvoiceIndex) {
                $booking = null;
                $crmId = 'N/A';
                $invoice_generate = null;

                // Determine which booking to use and extract CRM ID
                if ($cashImage->relatable_type === 'App\Models\Booking') {
                    if ($cashImage->relatable_id > 0 && $cashImage->relatable) {
                        // Case 1: Direct polymorphic relationship (relatable_id > 0)
                        $booking = $cashImage->relatable;
                        $crmId = $booking->crm_id ?? 'N/A';
                    } elseif ($cashImage->relatable_id == 0 && $cashImage->bookings && $cashImage->bookings->count() > 0) {
                        // Case 2: Many-to-many relationship (relatable_id = 0)
                        // Use the first booking or you can modify this logic based on your needs
                        $booking = $cashImage->bookings->first();
                        $crmId = $booking->crm_id ?? 'N/A';
                    }
                }

                if ($booking) {

                    $balance = $booking->grand_total - $booking->commission;
                    $booking->sub_total_with_vat = $balance;
                    $booking->vat = $balance - ($balance / 1.07);
                    $booking->total_excluding_vat = $balance - $booking->vat;

                    // Process items grouping
                    $booking = $this->processBookingItems($booking);
                    $invoice_generate = "INV".date('m', strtotime($booking->booking_date))."000".$nextInvoiceIndex+1;
                    $booking->invoice_generate = $invoice_generate;
                    $nextInvoiceIndex++;
                }

                return [
                    'cash_image_id' => $cashImage->id,
                    'crm_id' => $this->getCrmIdFromCashImage($cashImage),
                    'image' => Storage::url('images/' . $cashImage->image),
                    'booking' => $booking,
                ];
            });

            return [
                'result' => $transformedData->toArray()
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
     * Process booking items and group by product type
     */
    private function processBookingItems($booking) {
        if (!$booking->items || $booking->items->count() === 0) {
            $booking->grouped_items = collect([]);
            return $booking;
        }

        $groupedItems = $booking->items->groupBy('product_type')->map(function ($items, $productType) {
            // Get all service dates and format them
            $serviceDates = $items->whereNotNull('service_date')
                                ->pluck('service_date')
                                ->unique()
                                ->sort()
                                ->map(function($date) {
                                    return date('Y-m-d', strtotime($date));
                                })
                                ->values()
                                ->toArray();

            // Get all unique comments
            $comments = $items->whereNotNull('comment')
                             ->pluck('comment')
                             ->unique()
                             ->filter()
                             ->implode(', ');

            return [
                'product_type' => $productType,
                'product_name' => $this->getProductNameByType($productType),
                'quantity' => $items->sum('quantity'),
                'amount' => round($items->sum('amount'), 2),
                'discount' => round($items->sum('discount'), 2),
                'selling_price' => round($items->sum('selling_price'), 2),
                'total_cost_price' => round($items->sum('total_cost_price'), 2),
                'days' => $items->sum('days'),
                'service_dates' => $serviceDates,
                'service_dates_string' => implode(', ', $serviceDates),
                'comments' => $comments,
                'items_count' => $items->count(),

            ];
        })->values();

        $booking->grouped_items = $groupedItems;
        return $booking;
    }

    /**
     * Get human-readable product name based on product type
     */
    private function getProductNameByType($productType) {
        $productTypeMap = [
            'App\\Models\\Hotel' => 'Hotel Service',
            'App\\Models\\EntranceTicket' => 'Ticket Service',
            'App\\Models\\PrivateVanTour' => 'Car Rental Service',
        ];

        return $productTypeMap[$productType] ?? 'General Service';
    }

    private function preCalculateAllDepositNumbers($filters)
    {
        // Get ALL cash images that match the filters (without pagination)
        $allQuery = CashImage::select([
            'cash_images.id',
            'cash_images.date',
            'cash_images.relatable_id',
            'cash_images.relatable_type'
        ]);

        // Apply same filters as main query (except sorting for deposit calculation)
        if (!empty($filters['date'])) {
            $this->applyDateFilter($allQuery, $filters['date']);
        }
        $this->applySearchFilters($allQuery, $filters);

        // Load relationships for CRM ID access
        $allQuery->with([
            'relatable' => function ($morphQuery) {
                $morphQuery->when($morphQuery->getModel() instanceof \App\Models\BookingItemGroup, function ($q) {
                    $q->with('booking:id,crm_id');
                });
            },
            'bookings:id,crm_id'
        ]);

        $allCashImages = $allQuery->get();

        // Group by CRM ID
        $crmGroups = [];

        foreach ($allCashImages as $cashImage) {
            $crmId = $this->getCrmIdFromCashImage($cashImage);

            if ($crmId) {
                if (!isset($crmGroups[$crmId])) {
                    $crmGroups[$crmId] = [];
                }
                $crmGroups[$crmId][] = $cashImage;
            }
        }

        // For each CRM group, sort by date and assign deposit numbers
        foreach ($crmGroups as $crmId => $cashImageGroup) {
            // Sort by date (earliest first) - this is the CORRECT order for deposits
            usort($cashImageGroup, function($a, $b) {
                return strtotime($a->date) - strtotime($b->date);
            });

            // Assign deposit numbers
            foreach ($cashImageGroup as $index => $cashImage) {
                $depositNumber = $index + 1;
                $totalInGroup = count($cashImageGroup);

                if ($totalInGroup == 1) {
                    $this->crmDepositCounters[$cashImage->id] = 'final deposit';
                } else {
                    if ($index == $totalInGroup - 1) {
                        $this->crmDepositCounters[$cashImage->id] = 'final deposit';
                    } else {
                        $this->crmDepositCounters[$cashImage->id] = 'deposit ' . $depositNumber;
                    }
                }
            }
        }
    }

    /**
     * Get CRM ID from cash image (handle both polymorphic and many-to-many cases)
     */
    private function getCrmIdFromCashImage($cashImage)
    {
        if ($cashImage->relatable_type === 'App\Models\Booking') {
            if ($cashImage->relatable_id > 0 && $cashImage->relatable) {
                // Single booking case (polymorphic)
                return $cashImage->relatable->crm_id ?? null;
            } elseif ($cashImage->relatable_id == 0 && $cashImage->bookings && $cashImage->bookings->count() > 0) {
                // Multiple bookings case (many-to-many) - use first booking's CRM ID
                $firstBooking = $cashImage->bookings->first();
                return $firstBooking->crm_id ?? null;
            }
        } elseif ($cashImage->relatable_type === 'App\Models\BookingItemGroup' && $cashImage->relatable) {
            // BookingItemGroup case - get CRM ID through booking relationship
            if ($cashImage->relatable->relationLoaded('booking')) {
                return $cashImage->relatable->booking->crm_id ?? null;
            }
        }

        return null;
    }

    /**
     * Build summary query (Simple version)
     */
    private function buildSummaryQuery($filters)
    {
        $query = CashImage::select([
            'cash_images.id',
            'cash_images.image',
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

        if (!empty($filters['date'])) {
            $this->applyDateFilter($query, $filters['date']);
        }

        $this->applySearchFilters($query, $filters);
        $this->applySorting($query, $filters);

        // Simple loading for both cases
        $query->with([
            'relatable' => function ($morphQuery) {
                // Load only if relatable_id > 0
            },
            'bookings' => function ($bookingsQuery) {
                // Load only if relatable_id = 0
                $bookingsQuery->with(['items.product', 'customer']);
            }
        ]);

        return $query;
    }

    private function onlyImageForm($cashImage){
        $result = [
            'cash_image_id' => $cashImage->id,
            'crm_id' => null,
            'image' => $cashImage->image,
        ];

        if ($cashImage->relatable_type === 'App\Models\Booking') {
            if ($cashImage->relatable_id > 0 && $cashImage->relatable) {
                // Single booking case (polymorphic)
                $booking = $cashImage->relatable;
                $this->fillBookingData($result, $booking);
            } elseif ($cashImage->relatable_id == 0 && $cashImage->bookings && $cashImage->bookings->count() > 0) {
                // Multiple bookings case (many-to-many) - အရိုးရှင်းဆုံး: ပထမဆုံး booking ကိုပဲ ယူမယ်
                $firstBooking = $cashImage->bookings->first();
                $this->fillBookingData($result, $firstBooking);

                // Or you can aggregate all bookings data if needed:
                // $this->fillAggregatedBookingsData($result, $cashImage->bookings);
            }
        }

        return $result;
    }

    /**
     * Transform cash image data to summary format (Simple handling for both cases)
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
            'deposit' => 'final deposit',
        ];

        if ($cashImage->relatable_type === 'App\Models\Booking') {
            if ($cashImage->relatable_id > 0 && $cashImage->relatable) {
                // Single booking case (polymorphic)
                $booking = $cashImage->relatable;
                $this->fillBookingData($summary, $booking);
            } elseif ($cashImage->relatable_id == 0 && $cashImage->bookings && $cashImage->bookings->count() > 0) {
                // Multiple bookings case (many-to-many) - အရိုးရှင်းဆုံး: ပထမဆုံး booking ကိုပဲ ယူမယ်
                $firstBooking = $cashImage->bookings->first();
                $this->fillBookingData($summary, $firstBooking);

                // Or you can aggregate all bookings data if needed:
                // $this->fillAggregatedBookingsData($summary, $cashImage->bookings);
            }
        }

        // Set deposit value from pre-calculated counters
        if (isset($this->crmDepositCounters[$cashImage->id])) {
            $summary['deposit'] = $this->crmDepositCounters[$cashImage->id];
        }

        return $summary;
    }

    /**
     * Fill booking data into summary (Simple helper)
     */
    private function fillBookingData(&$summary, $booking)
    {
        $summary['invoice_id'] = $booking->id ?? null;
        $summary['crm_id'] = $booking->crm_id ?? null;
        $summary['customer_name'] = optional($booking->customer)->name;

        if (!$booking->relationLoaded('items') && $booking->id) {
            $booking->load('items.product');
        }

        // Calculate service totals
        $hotelTotal = 0; $hotelCost = 0; $hotelVat = 0; $hotelCommission = 0;
        $ticketTotal = 0; $ticketCost = 0; $ticketVat = 0; $ticketCommission = 0;

        if ($booking->items && $booking->items->count() > 0) {
            foreach ($booking->items as $item) {
                $productType = $item->product_type ?? null;
                $amount = $item->amount ?? 0;
                $cost = $item->total_cost_price ?? 0;
                $vat = $item->output_vat ?? 0;
                $commission = $item->commission ?? 0;

                if ($productType === 'App\\Models\\Hotel') {
                    $hotelTotal += $amount; $hotelCost += $cost;
                    $hotelVat += $vat; $hotelCommission += $commission;
                } elseif ($productType === 'App\\Models\\EntranceTicket') {
                    $ticketTotal += $amount; $ticketCost += $cost;
                    $ticketVat += $vat; $ticketCommission += $commission;
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

    // ... rest of the existing methods remain the same ...

    public function getAvailableRelatableTypes()
    {
        try {
            $types = CashImage::select('relatable_type')
                ->distinct()
                ->whereNotNull('relatable_type')
                ->pluck('relatable_type')
                ->toArray();

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

    public function getSummaryStatistics(Request $request)
    {
        try {
            $filters = $this->extractFilters($request);

            $query = CashImage::select([
                'cash_images.id',
                'cash_images.amount',
                'cash_images.currency',
                'cash_images.relatable_id',
                'cash_images.relatable_type'
            ]);

            if (!empty($filters['date'])) {
                $this->applyDateFilter($query, $filters['date']);
            }
            $this->applySearchFilters($query, $filters);

            $cashImages = $query->get();

            $statistics = [
                'total_cash_records' => $cashImages->count(),
                'total_cash_amount_by_currency' => [],
                'booking_related_count' => 0,
                'multiple_booking_connections' => 0, // relatable_id = 0 အရေအတွက်
                'single_booking_connections' => 0,   // relatable_id > 0 အရေအတွက်
                'average_transaction_amount' => 0
            ];

            $currencyGroups = $cashImages->groupBy('currency');
            foreach ($currencyGroups as $currency => $items) {
                $statistics['total_cash_amount_by_currency'][$currency] = $items->sum('amount');
            }

            $bookingRelated = $cashImages->where('relatable_type', 'App\Models\Booking');
            $statistics['booking_related_count'] = $bookingRelated->count();
            $statistics['multiple_booking_connections'] = $bookingRelated->where('relatable_id', 0)->count();
            $statistics['single_booking_connections'] = $bookingRelated->where('relatable_id', '>', 0)->count();

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
