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
            'sort_order' => $request->input('sort_order')
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

    /**
     * Get all cash images summary (Simple version for many-to-many)
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
     * Build summary query (Simple version)
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
