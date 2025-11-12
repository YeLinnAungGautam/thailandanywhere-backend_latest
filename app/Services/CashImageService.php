<?php

namespace App\Services;

use App\Http\Resources\Accountance\CashImageListResource as AccountanceCashImageResource;
use App\Http\Resources\Accountance\CashParchaseDetailResource;
use App\Models\CashImage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CashImageService
{
    const PER_PAGE = 10;
    const MAX_PER_PAGE = 1000;

    const VALID_INTERACT_BANK = [
        'personal', 'company', 'all', 'cash_at_office', 'to_money_changer', 'deposit_management', 'pay_to_driver'
    ];

    const VALID_CURRENCY = ['MMK', 'THB', 'USD'];

    const VALID_RELATABLE_TYPES = [
        'App\Models\Booking',
        'App\Models\BookingItemGroup',
        'App\Models\CashBook',
    ];

    const VALID_SORTS = [
        'date', 'sender', 'receiver', 'amount', 'interact_bank', 'currency', 'created_at'
    ];

    private $crmDepositCounters = [];

    /**
     * Get all cash images with filtering and pagination
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
                'sort_by' => 'nullable|in:' . implode(',', self::VALID_SORTS),
                'sort_order' => 'nullable|in:asc,desc',
                'sender' => 'nullable|string|max:255',
                'receiver' => 'nullable|string|max:255',
                'data_verify' => 'nullable|in:0,1,true,false',
                'internal_transfer' => 'nullable|in:0,1,true,false',
                'amount' => 'nullable|numeric|min:0',
                'date' => 'nullable|string',
                'crm_id' => 'nullable|string|max:255',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:' . self::MAX_PER_PAGE,
                'filter_type' => 'nullable|in:tax_receipt_have,tax_receipt_missing,invoice_have,invoice_missing',
                'filter_type_invoice' => 'nullable|in:invoice_have,invoice_missing',
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
     * Build optimized query
     */
    private function buildOptimizedQuery($filters)
    {
        $query = CashImage::select([
            'id', 'date', 'sender', 'receiver', 'amount', 'interact_bank', 'currency',
            'data_verify', 'internal_transfer', 'image', 'created_at', 'updated_at',
            'relatable_id', 'relatable_type', 'bank_verify'
        ]);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        // Load relationships with explicit field selection
        $query->with([
            'relatable' => function ($q) {
                $modelClass = get_class($q->getModel());

                if ($modelClass === 'App\Models\Booking') {
                    $q->select(['id', 'crm_id', 'customer_id'])
                        ->with(['customer' => function ($customerQuery) {
                            $customerQuery->select('id', 'name');
                        }]);
                } elseif ($modelClass === 'App\Models\BookingItemGroup') {
                    $q->select(['id', 'booking_id'])
                        ->with(['booking' => function ($bookingQuery) {
                            $bookingQuery->select(['id', 'crm_id', 'customer_id'])
                                ->with(['customer' => function ($customerQuery) {
                                    $customerQuery->select('id', 'name');
                                }]);
                        }]);
                }
            },
            'cashBookings' => function ($q) {
                $q->select(
                    'bookings.id',
                    'bookings.crm_id',
                    'bookings.invoice_number',
                    'bookings.grand_total',
                    'bookings.customer_id'
                )
                    ->with(['customer' => function ($customerQuery) {
                        $customerQuery->select('id', 'name');
                    }]);
            },
            'cashBookingItemGroups' => function ($q) {
                $q->select('booking_item_groups.id', 'booking_id')
                    ->with(['booking' => function ($bookingQuery) {
                        $bookingQuery->select(['id', 'crm_id', 'customer_id'])
                            ->with(['customer' => function ($customerQuery) {
                                $customerQuery->select('id', 'name');
                            }]);
                    }]);
            },
            'cashBooks'
        ]);

        return $query;
    }

    /**
     * Apply all filters
     */
    private function applyFilters($query, $filters)
    {
        if (!empty($filters['date'])) {
            $this->applyDateFilter($query, $filters['date']);
        }

        if (isset($filters['data_verify']) && $filters['data_verify'] !== null && $filters['data_verify'] !== '') {
            $query->where('data_verify', $filters['data_verify']);
        }

        if (isset($filters['bank_verify']) && $filters['bank_verify'] !== null && $filters['bank_verify'] !== '') {
            $query->where('bank_verify', $filters['bank_verify']);
        }

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

        if (!empty($filters['crm_id'])) {
            $this->applyCrmIdFilter($query, $filters['crm_id'], $filters['relatable_type'] ?? null);
        }

        if (!empty($filters['filter_type'])) {
            $this->applyFilterType($query, $filters['filter_type']);
        }

        if (!empty($filters['filter_type_invoice'])) {
            $this->applyFilterType($query, $filters['filter_type_invoice']);
        }
    }

    /**
     * Apply CRM ID filter - FIXED VERSION
     */
    private function applyCrmIdFilter($query, $crmId, $relatableType = null)
    {
        $query->where(function ($mainQuery) use ($crmId, $relatableType) {
            if ($relatableType === 'App\Models\Booking' || !$relatableType) {
                // Search in Booking
                $mainQuery->orWhere(function ($bookingQ) use ($crmId) {
                    // Polymorphic (relatable_id > 0)
                    $bookingQ->where(function ($q) use ($crmId) {
                        $q->where('relatable_type', 'App\Models\Booking')
                            ->where('relatable_id', '>', 0)
                            ->whereIn('relatable_id', function ($subQ) use ($crmId) {
                                $subQ->select('id')
                                    ->from('bookings')
                                    ->where('crm_id', 'like', '%' . $crmId . '%');
                            });
                    });

                    // Many-to-many (relatable_id = 0)
                    $bookingQ->orWhere(function ($q) use ($crmId) {
                        $q->where('relatable_id', 0)
                            ->whereHas('cashBookings', function ($subQ) use ($crmId) {
                                $subQ->where('crm_id', 'like', '%' . $crmId . '%');
                            });
                    });
                });
            }

            if ($relatableType === 'App\Models\BookingItemGroup' || !$relatableType) {
                // Search in BookingItemGroup
                $mainQuery->orWhere(function ($itemGroupQ) use ($crmId) {
                    // Polymorphic (relatable_id > 0)
                    $itemGroupQ->where(function ($q) use ($crmId) {
                        $q->where('relatable_type', 'App\Models\BookingItemGroup')
                            ->where('relatable_id', '>', 0)
                            ->whereIn('relatable_id', function ($subQ) use ($crmId) {
                                $subQ->select('id')
                                    ->from('booking_item_groups')
                                    ->whereIn('booking_id', function ($bookingSubQ) use ($crmId) {
                                        $bookingSubQ->select('id')
                                            ->from('bookings')
                                            ->where('crm_id', 'like', '%' . $crmId . '%');
                                    });
                            });
                    });

                    // Many-to-many (relatable_id = 0)
                    $itemGroupQ->orWhere(function ($q) use ($crmId) {
                        $q->where('relatable_id', 0)
                            ->whereHas('cashBookingItemGroups', function ($subQ) use ($crmId) {
                                $subQ->whereHas('booking', function ($bookingQ) use ($crmId) {
                                    $bookingQ->where('crm_id', 'like', '%' . $crmId . '%');
                                });
                            });
                    });
                });
            }
        });
    }

    /**
     * Apply filter type
     */
    private function applyFilterType($query, $type)
    {
        switch ($type) {
            case 'tax_receipt_have':
                $query->where('relatable_type', 'App\Models\BookingItemGroup')
                    ->whereHas('relatable', function ($q) {
                        $q->has('taxReceipts');
                    });

                break;

            case 'tax_receipt_missing':
                $query->where('relatable_type', 'App\Models\BookingItemGroup')
                    ->whereHas('relatable', function ($q) {
                        $q->doesntHave('taxReceipts');
                    });

                break;

            case 'invoice_have':
                $query->where('relatable_type', 'App\Models\BookingItemGroup')
                    ->whereExists(function ($existsQuery) {
                        $existsQuery->select(DB::raw(1))
                            ->from('customer_documents')
                            ->whereColumn('customer_documents.booking_item_group_id', 'cash_images.relatable_id')
                            ->where('customer_documents.type', 'booking_confirm_letter');
                    });

                break;

            case 'invoice_missing':
                $query->where('relatable_type', 'App\Models\BookingItemGroup')
                    ->whereNotExists(function ($existsQuery) {
                        $existsQuery->select(DB::raw(1))
                            ->from('customer_documents')
                            ->whereColumn('customer_documents.booking_item_group_id', 'cash_images.relatable_id')
                            ->where('customer_documents.type', 'booking_confirm_letter');
                    });

                break;
        }
    }

    /**
     * Apply sorting
     */
    private function applySorting($query, $filters)
    {
        if (!empty($filters['sort_by']) && !empty($filters['sort_order'])) {
            $sortBy = $filters['sort_by'];
            $sortOrder = strtolower($filters['sort_order']);

            if (in_array($sortBy, ['sender', 'receiver'])) {
                $query->orderByRaw("LOWER($sortBy) " . strtoupper($sortOrder));
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
            $query->whereDate('date', '>=', $dates[0])
                ->whereDate('date', '<=', $dates[1]);
        } else {
            $query->whereDate('date', $dates[0]);
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
            'data_verify' => $request->input('data_verify'),
            'bank_verify' => $request->input('bank_verify'),
            'crm_id' => $request->input('crm_id'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
            'filter_type' => $request->input('filter_type'),
            'filter_type_invoice' => $request->input('filter_type_invoice'),
        ];
    }

    /**
     * Get basic list
     */
    public function getBasicList(Request $request)
    {
        try {
            $limit = min((int) $request->get('limit', self::PER_PAGE), self::MAX_PER_PAGE);

            $query = CashImage::select([
                'id', 'date', 'sender', 'receiver', 'amount', 'interact_bank',
                'currency', 'created_at', 'image', 'relatable_type', 'relatable_id'
            ]);

            if ($request->filled('date')) {
                $query->whereDate('date', $request->input('date'));
            }

            if ($request->filled('relatable_type') && in_array($request->input('relatable_type'), self::VALID_RELATABLE_TYPES)) {
                $query->where('relatable_type', $request->input('relatable_type'));
            }

            if ($request->filled('sort_by') && in_array($request->input('sort_by'), self::VALID_SORTS)) {
                $sortOrder = in_array($request->input('sort_order'), ['asc', 'desc'])
                    ? $request->input('sort_order')
                    : 'desc';
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
     * Get CRM ID from cash image
     */
    private function getCrmIdFromCashImage($cashImage)
    {
        // 1. Polymorphic relatable (relatable_id > 0)
        if ($cashImage->relatable_id > 0 && $cashImage->relatable) {
            if ($cashImage->relatable_type === 'App\Models\Booking') {
                return $cashImage->relatable->crm_id ?? null;
            }

            if ($cashImage->relatable_type === 'App\Models\BookingItemGroup' && $cashImage->relatable->booking) {
                return $cashImage->relatable->booking->crm_id ?? null;
            }
        }

        // 2. Many-to-many relationships (relatable_id = 0)
        if ($cashImage->relatable_id == 0) {
            // cashBookings
            if ($cashImage->relationLoaded('cashBookings') && $cashImage->cashBookings->count() > 0) {
                return $cashImage->cashBookings->first()->crm_id ?? null;
            }

            // cashBookingItemGroups
            if ($cashImage->relationLoaded('cashBookingItemGroups') && $cashImage->cashBookingItemGroups->count() > 0) {
                $firstGroup = $cashImage->cashBookingItemGroups->first();
                if ($firstGroup && $firstGroup->booking) {
                    return $firstGroup->booking->crm_id ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Pre-calculate deposit numbers for all records
     */
    private function preCalculateAllDepositNumbers($filters)
    {
        $allQuery = CashImage::select(['id', 'date', 'relatable_id', 'relatable_type']);

        if (!empty($filters['date'])) {
            $this->applyDateFilter($allQuery, $filters['date']);
        }
        $this->applyFilters($allQuery, $filters);

        $allQuery->with([
            'relatable' => function ($q) {
                $q->when($q->getModel() instanceof \App\Models\Booking, function ($bookingQ) {
                    $bookingQ->select('id', 'crm_id');
                })->when($q->getModel() instanceof \App\Models\BookingItemGroup, function ($groupQ) {
                    $groupQ->select('id', 'booking_id')->with('booking:id,crm_id');
                });
            },
            'cashBookings:id,crm_id',
            'cashBookingItemGroups.booking:id,crm_id'
        ]);

        $allCashImages = $allQuery->get();

        // Group by CRM ID
        $crmGroups = [];
        foreach ($allCashImages as $cashImage) {
            $crmId = $this->getCrmIdFromCashImage($cashImage);
            if ($crmId) {
                $crmGroups[$crmId][] = $cashImage;
            }
        }

        // Assign deposit numbers
        foreach ($crmGroups as $cashImageGroup) {
            usort($cashImageGroup, function ($a, $b) {
                return strtotime($a->date) <=> strtotime($b->date);
            });

            $totalInGroup = count($cashImageGroup);
            foreach ($cashImageGroup as $index => $cashImage) {
                if ($totalInGroup == 1 || $index == $totalInGroup - 1) {
                    $this->crmDepositCounters[$cashImage->id] = 'final deposit';
                } else {
                    $this->crmDepositCounters[$cashImage->id] = 'deposit ' . ($index + 1);
                }
            }
        }
    }

    /**
     * Get all cash images summary
     */
    public function getAllSummary(Request $request)
    {
        try {
            $this->validateRequest($request);

            $limit = min((int) $request->get('limit', self::PER_PAGE), self::MAX_PER_PAGE);
            $filters = $this->extractFilters($request);

            $this->crmDepositCounters = [];
            $this->preCalculateAllDepositNumbers($filters);

            $query = $this->buildSummaryQuery($filters);
            $data = $query->paginate($limit);

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
     * Build summary query
     */
    private function buildSummaryQuery($filters)
    {
        $query = CashImage::select([
            'id', 'image', 'date', 'sender', 'receiver', 'amount', 'interact_bank',
            'currency', 'created_at', 'relatable_id', 'relatable_type'
        ]);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        $query->with([
            'relatable' => function ($q) {
                $q->when($q->getModel() instanceof \App\Models\Booking, function ($bookingQ) {
                    $bookingQ->with(['items.product', 'customer']);
                })->when($q->getModel() instanceof \App\Models\BookingItemGroup, function ($groupQ) {
                    $groupQ->with('booking.items.product', 'booking.customer');
                });
            },
            'cashBookings' => function ($q) {
                $q->with(['items.product', 'customer']);
            },
            'cashBookingItemGroups' => function ($q) {
                $q->with('booking.items.product', 'booking.customer');
            }
        ]);

        return $query;
    }

    /**
     * Transform cash image to summary
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
            'deposit' => $this->crmDepositCounters[$cashImage->id] ?? 'final deposit',
        ];

        // Get booking
        $booking = null;
        if ($cashImage->relatable_id > 0 && $cashImage->relatable_type === 'App\Models\Booking' && $cashImage->relatable) {
            $booking = $cashImage->relatable;
        } elseif ($cashImage->relatable_id == 0 && $cashImage->relationLoaded('cashBookings') && $cashImage->cashBookings->count() > 0) {
            $booking = $cashImage->cashBookings->first();
        }

        if ($booking) {
            $this->fillBookingData($summary, $booking);
        }

        return $summary;
    }

    /**
     * Fill booking data into summary
     */
    private function fillBookingData(&$summary, $booking)
    {
        $summary['invoice_id'] = $booking->id ?? null;
        $summary['crm_id'] = $booking->crm_id ?? null;
        $summary['customer_name'] = optional($booking->customer)->name;

        if (!$booking->relationLoaded('items') && $booking->id) {
            $booking->load('items.product');
        }

        $hotelTotal = 0;
        $hotelCost = 0;
        $hotelVat = 0;
        $hotelCommission = 0;
        $ticketTotal = 0;
        $ticketCost = 0;
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

    /**
     * Get all summary for export
     */
    public function getAllSummaryForExport(Request $request)
    {
        try {
            $this->validateRequest($request);
            $filters = $this->extractFilters($request);

            $this->crmDepositCounters = [];
            $this->preCalculateAllDepositNumbers($filters);

            $query = $this->buildSummaryQuery($filters);
            $allData = $query->get();

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

    /**
     * Get all purchase for export
     */
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
                'message' => 'An error occurred. Error: ' . $e->getMessage(),
                'result' => null
            ];
        }
    }

    /**
     * Get purchase with limit for export
     */
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
                'result' => ['data' => $resourceCollection->response()->getData(true),
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
                'message' => 'An error occurred. Error: ' . $e->getMessage(),
                'result' => null
            ];
        }
    }

    /**
     * Get all grouped by product for export
     */
    public function getAllGroupedByProductForExport(Request $request)
    {
        try {
            $this->validateRequest($request);
            $filters = $this->extractFilters($request);

            $page = $request->get('page', 1);
            $limit = $request->get('limit', 100);

            $query = CashImage::select([
                'cash_images.id', 'cash_images.date', 'cash_images.sender', 'cash_images.receiver',
                'cash_images.amount', 'cash_images.interact_bank', 'cash_images.currency',
                'cash_images.image', 'cash_images.relatable_id', 'cash_images.relatable_type',
                'cash_images.created_at'
            ])
                ->where('relatable_type', 'App\Models\BookingItemGroup')
                ->where('relatable_id', '>', 0);

            $this->applyFilters($query, $filters);

            $query->with([
                'relatable' => function ($q) {
                    $q->select(['id', 'booking_id'])
                        ->with([
                            'booking' => function ($bookingQuery) {
                                $bookingQuery->select(['id', 'crm_id', 'customer_id'])
                                    ->with(['items.product', 'customer']);
                            }
                        ]);
                }
            ]);

            $allData = $query->get();

            // Batch get tax receipt counts
            $bookingItemGroupIds = $allData->pluck('relatable_id')->filter()->toArray();
            $taxReceiptCounts = $this->getBatchTaxReceiptCounts($bookingItemGroupIds);

            // Group by product
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

            // Sort by total records
            uasort($groupedByProduct, function ($a, $b) {
                return $b['total_records'] <=> $a['total_records'];
            });

            $groupedArray = array_values($groupedByProduct);
            $totalGroups = count($groupedArray);
            $totalCashImages = $allData->count();

            // Pagination
            $offset = ($page - 1) * $limit;
            $paginatedGroups = array_slice($groupedArray, $offset, $limit);

            $totalPages = ceil($totalGroups / $limit);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;

            // Overall summary
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
                'message' => 'An error occurred. Error: ' . $e->getMessage(),
                'result' => null
            ];
        }
    }

    /**
     * Extract products from BookingItemGroup
     */
    private function extractProductsFromBookingItemGroup($cashImage)
    {
        if (!$cashImage->relatable || !$cashImage->relatable->booking) {
            return [[
                'product_name' => 'Uncategorized',
                'product_type' => 'General'
            ]];
        }

        $products = [];
        $booking = $cashImage->relatable->booking;

        if ($booking->items && $booking->items->count() > 0) {
            foreach ($booking->items as $bookingItem) {
                if ($bookingItem->product) {
                    $products[] = [
                        'product_name' => $bookingItem->product->name ?? 'Unknown Product',
                        'product_type' => $this->getProductTypeDisplayName($bookingItem->product_type)
                    ];
                } elseif ($bookingItem->product_id) {
                    $products[] = [
                        'product_name' => 'Product ID: ' . $bookingItem->product_id,
                        'product_type' => $this->getProductTypeDisplayName($bookingItem->product_type ?? null)
                    ];
                }
            }
        }

        $products = array_unique($products, SORT_REGULAR);

        return empty($products) ? [[
            'product_name' => 'Uncategorized',
            'product_type' => 'General'
        ]] : $products;
    }

    /**
     * Batch fetch tax receipt counts
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
     * Get simple tax receipt status
     */
    private function getSimpleTaxReceiptStatus($cashImage, $taxReceiptCounts)
    {
        if ($cashImage->relatable_type !== 'App\Models\BookingItemGroup' || !$cashImage->relatable_id) {
            return 'not_applicable';
        }

        return isset($taxReceiptCounts[$cashImage->relatable_id]) ? 'have' : 'missing';
    }

    /**
     * Get product type display name
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
     * Get purchase for print batch
     */
    public function getAllPurchaseForPrintBatch(Request $request, int $offset, int $limit)
    {
        try {
            $this->validateRequest($request);
            $filters = $this->extractFilters($request);

            $query = $this->buildOptimizedQuery($filters);
            $data = $query->offset($offset)->limit($limit)->get();

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
                'message' => 'An error occurred. Error: ' . $e->getMessage(),
                'result' => null
            ];
        }
    }

    /**
     * Get only images with booking data
     */
    public function onlyImages(Request $request)
    {
        try {
            $this->validateRequest($request);
            $filters = $this->extractFilters($request);

            $query = CashImage::select([
                'id', 'image', 'relatable_id', 'relatable_type', 'date',
                'sender', 'receiver', 'amount', 'interact_bank', 'currency', 'created_at'
            ]);

            $this->applyFilters($query, $filters);
            $this->applySorting($query, $filters);

            // Apply pagination if batch parameters are provided
            if ($request->has('batch_offset') && $request->has('batch_limit')) {
                $query->skip($request->batch_offset)
                      ->take($request->batch_limit);
            }

            $query->with([
                'relatable' => function ($q) {
                    $q->when($q->getModel() instanceof \App\Models\Booking, function ($bookingQ) {
                        $bookingQ->select([
                            'id', 'crm_id', 'grand_total', 'customer_id', 'commission',
                            'created_at', 'start_date', 'end_date', 'booking_date',
                            'payment_method', 'payment_status', 'bank_name', 'discount', 'sub_total',
                        ])->with([
                            'customer' => function ($customerQ) {
                                $customerQ->select(['id', 'name', 'phone_number']);
                            },
                            'items' => function ($itemsQ) {
                                $itemsQ->select([
                                    'id', 'booking_id', 'product_id', 'quantity', 'selling_price',
                                    'total_cost_price', 'discount', 'product_type', 'amount',
                                    'comment', 'service_date', 'days', 'checkin_date', 'checkout_date',
                                ])->with('product');
                            }
                        ]);
                    });
                },
                'cashBookings' => function ($q) {
                    $q->select([
                        'bookings.id', 'bookings.crm_id', 'bookings.grand_total', 'bookings.customer_id',
                        'bookings.commission', 'bookings.created_at', 'bookings.start_date', 'bookings.end_date',
                        'bookings.booking_date', 'bookings.payment_method', 'bookings.payment_status',
                        'bookings.bank_name', 'bookings.discount', 'bookings.sub_total',
                    ])->with([
                        'customer' => function ($customerQ) {
                            $customerQ->select(['id', 'name', 'phone_number']);
                        },
                        'items' => function ($itemsQ) {
                            $itemsQ->select([
                                'id', 'booking_id', 'product_id', 'quantity', 'selling_price',
                                'total_cost_price', 'discount', 'product_type', 'amount',
                                'comment', 'service_date', 'days', 'checkin_date', 'checkout_date',
                            ])->with('product');
                        }
                    ]);
                }
            ]);

            $allData = $query->get();

            // Group by month for invoice generation
            $invoiceCounters = [];

            // Get starting invoice number for this batch
            $invoiceStartNumber = $request->has('invoice_start_number')
                ? $request->invoice_start_number
                : 1;

            // Global counter for sequential numbering across all items in this batch
            $globalCounter = $invoiceStartNumber - 1;

            $transformedData = $allData->map(function ($cashImage) use (&$invoiceCounters, &$globalCounter) {
                $booking = null;

                if ($cashImage->relatable_type === 'App\Models\Booking') {
                    if ($cashImage->relatable_id > 0 && $cashImage->relatable) {
                        $booking = $cashImage->relatable;
                    } elseif ($cashImage->relatable_id == 0 && $cashImage->cashBookings && $cashImage->cashBookings->count() > 0) {
                        $booking = $cashImage->cashBookings->first();
                    }
                }

                if ($booking) {
                    $balance = $booking->grand_total - $booking->commission;
                    $booking->sub_total_with_vat = $balance;
                    $booking->vat = $balance - ($balance / 1.07);
                    $booking->total_excluding_vat = $balance - $booking->vat;

                    $booking = $this->processBookingItems($booking);

                    // Increment global counter for this item
                    $globalCounter++;

                    $cashImageDate = $cashImage->date ?: $cashImage->created_at;
                    $month = date('m', strtotime($cashImageDate));

                    // Generate invoice with global counter
                    $invoice_generate = "INV" . $month . str_pad($globalCounter, 6, '0', STR_PAD_LEFT);
                    $booking->invoice_generate = $invoice_generate;

                    // Track counters per month (optional, for reference)
                    if (!isset($invoiceCounters[$month])) {
                        $invoiceCounters[$month] = 0;
                    }
                    $invoiceCounters[$month]++;
                }

                $bookingArray = null;
                if ($booking) {
                    $bookingArray = $booking->toArray();

                    if ($booking->customer) {
                        $bookingArray['customer'] = $booking->customer->toArray();
                    }

                    if ($booking->items) {
                        $bookingArray['items'] = $booking->items->map(function($item) {
                            $itemArray = $item->toArray();
                            if ($item->product) {
                                $itemArray['product'] = $item->product->toArray();
                            }
                            return $itemArray;
                        })->toArray();
                    }

                    if (isset($booking->grouped_items)) {
                        $bookingArray['grouped_items'] = $booking->grouped_items;
                    }
                }

                return [
                    'cash_image_id' => $cashImage->id,
                    'crm_id' => $this->getCrmIdFromCashImage($cashImage),
                    'image' => $cashImage->image,
                    'cash_image_date' => $cashImage->date,
                    'bank' => $cashImage->interact_bank,
                    'currency' => $cashImage->currency,
                    'cash_amount' => $cashImage->amount,
                    'sender' => $cashImage->sender,
                    'receiver' => $cashImage->receiver,
                    'booking' => $bookingArray,
                ];
            });

            return [
                'status' => 1,
                'message' => 'Cash images with booking details retrieved successfully',
                'result' => $transformedData->values()->toArray(),
                'invoice_counters' => $invoiceCounters,
                'last_invoice_number' => $globalCounter, // Last number used in this batch
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'status' => 'Error has occurred.',
                'message' => 'Validation Error: ' . $e->getMessage(),
                'result' => null
            ];
        } catch (Exception $e) {
            Log::error('OnlyImages Error: ' . $e->getMessage());

            return [
                'status' => 'Error has occurred.',
                'message' => 'An error occurred. Error: ' . $e->getMessage(),
                'result' => null
            ];
        }
    }

    /**
     * Process booking items and group by product type
     */
    private function processBookingItems($booking)
    {
        if (!$booking->items || $booking->items->count() === 0) {
            $booking->grouped_items = collect([]);

            return $booking;
        }

        $groupedItems = $booking->items->groupBy('product_type')->map(function ($items, $productType) {
            $serviceDates = $items->whereNotNull('service_date')
                ->pluck('service_date')
                ->unique()
                ->sort()
                ->map(function ($date) {
                    return date('Y-m-d', strtotime($date));
                })
                ->values()
                ->toArray();

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
     * Get product name by type
     */
    private function getProductNameByType($productType)
    {
        $productTypeMap = [
            'App\\Models\\Hotel' => 'Hotel Service',
            'App\\Models\\EntranceTicket' => 'Ticket Service',
            'App\\Models\\PrivateVanTour' => 'Car Rental Service',
        ];

        return $productTypeMap[$productType] ?? 'General Service';
    }

    /**
     * Get duplicate cash images
     */
    public function duplicateCashImage(Request $request)
    {
        try {
            $validator = Validator(
                $request->all(),
                [
                    'date' => 'nullable|string',
                    'amount' => 'nullable|numeric|min:0',
                    'interact_bank' => 'nullable|in:' . implode(',', self::VALID_INTERACT_BANK),
                    'data_verify' => 'nullable|in:0,1,true,false',
                    'bank_verify' => 'nullable|in:0,1,true,false',
                    'currency' => 'nullable|in:' . implode(',', self::VALID_CURRENCY),
                    'limit' => 'nullable|integer|min:1|max:' . self::MAX_PER_PAGE,
                    'page' => 'nullable|integer|min:1',
                    'group_by_duplicate' => 'nullable|in:0,1,true,false',
                ]
            );

            if ($validator->fails()) {
                throw new InvalidArgumentException($validator->errors()->first());
            }

            if ($request->date) {
                $this->validateDateFormat($request->date);
            }

            $limit = min((int) $request->get('limit', self::PER_PAGE), self::MAX_PER_PAGE);
            $groupByDuplicate = filter_var($request->get('group_by_duplicate', false), FILTER_VALIDATE_BOOLEAN);

            $query = CashImage::select([
                'cash_images.*',
                DB::raw('COUNT(*) OVER (PARTITION BY date, amount, interact_bank) as duplicate_count')
            ]);

            if ($request->filled('date')) {
                $this->applyDateFilter($query, $request->date);
            }

            if ($request->filled('amount')) {
                $query->where('amount', $request->amount);
            }

            if ($request->filled('interact_bank')) {
                $query->where('interact_bank', $request->interact_bank);
            }

            if ($request->filled('currency')) {
                $query->where('currency', $request->currency);
            }

            if ($request->has('data_verify')) {
                $dataVerify = filter_var($request->data_verify, FILTER_VALIDATE_BOOLEAN);
                $query->where('data_verify', $dataVerify);
            }

            if ($request->has('bank_verify')) {
                $bankVerify = filter_var($request->bank_verify, FILTER_VALIDATE_BOOLEAN);
                $query->where('bank_verify', $bankVerify);
            }

            $query->with([
                'relatable',
                'cashBookings' => function ($q) {
                    $q->select('bookings.id', 'bookings.crm_id', 'bookings.invoice_number', 'bookings.grand_total', 'bookings.customer_id', 'bookings.commission')
                        ->with('customer:id,name');
                }
            ]);

            $query->orderBy('date', 'desc')
                ->orderBy('amount', 'desc')
                ->orderBy('interact_bank');

            if ($groupByDuplicate) {
                $allResults = $query->get();
                $duplicates = $allResults->filter(function ($item) {
                    return $item->duplicate_count > 1;
                });

                $groupedDuplicates = $duplicates->groupBy(function ($item) {
                    return $item->date . '|' . $item->amount . '|' . $item->interact_bank;
                })->map(function ($group, $key) use ($request) {
                    list($datetime, $amount, $interact_bank) = explode('|', $key);

                    $mockRequest = new \Illuminate\Http\Request([
                        'include_relatable' => true,
                        'include_bookings' => true
                    ]);

                    return [
                        'duplicate_signature' => [
                            'date' => $datetime,
                            'amount' => (float) $amount,
                            'interact_bank' => $interact_bank,
                        ],
                        'duplicate_count' => $group->count(),
                        'total_amount' => $group->sum('amount'),
                        'verified_count' => $group->where('data_verify', true)->count(),
                        'unverified_count' => $group->where('data_verify', false)->count(),
                        'bank_verified_count' => $group->where('bank_verify', true)->count(),
                        'bank_unverified_count' => $group->where('bank_verify', false)->count(),
                        'cash_images' => AccountanceCashImageResource::collection($group)
                            ->additional(['include_relatable' => true, 'include_bookings' => true])
                            ->resolve($mockRequest),
                    ];
                })->values();

                return [
                    'success' => true,
                    'data' => [
                        'duplicate_groups' => $groupedDuplicates,
                        'total_groups' => $groupedDuplicates->count(),
                        'total_duplicate_records' => $duplicates->count(),
                    ],
                    'message' => 'Duplicate cash images grouped successfully'
                ];

            } else {
                $results = $query->paginate($limit);
                $filteredResults = $results->getCollection()->filter(function ($item) {
                    return $item->duplicate_count > 1;
                });

                $results->setCollection($filteredResults);

                $mockRequest = new \Illuminate\Http\Request([
                    'include_relatable' => true,
                    'include_bookings' => true
                ]);

                $resourceCollection = AccountanceCashImageResource::collection($results)
                    ->additional(['include_relatable' => true, 'include_bookings' => true]);

                return [
                    'success' => true,
                    'data' => [
                        'duplicates' => $resourceCollection->response()->getData(true),
                        'summary' => [
                            'total_duplicate_records' => $results->total(),
                            'current_page_count' => $results->count(),
                        ]
                    ],
                    'message' => 'Duplicate cash images retrieved successfully'
                ];
            }

        } catch (InvalidArgumentException $e) {
            Log::error('Duplicate Cash Image Validation Error: ' . $e->getMessage());

            return [
                'success' => false,
                'data' => null,
                'message' => 'Validation Error: ' . $e->getMessage(),
                'error_type' => 'validation'
            ];
        } catch (Exception $e) {
            Log::error('Duplicate Cash Image Error: ' . $e->getMessage());

            return [
                'success' => false,
                'data' => null,
                'message' => 'An error occurred. Error: ' . $e->getMessage(),
                'error_type' => 'system'
            ];
        }
    }

    /**
     * Get available relatable types
     */
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

    /**
     * Get count by relatable type
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
     * Get summary statistics
     */
    public function getSummaryStatistics(Request $request)
    {
        try {
            $filters = $this->extractFilters($request);

            $query = CashImage::select([
                'cash_images.id', 'cash_images.amount', 'cash_images.currency',
                'cash_images.relatable_id', 'cash_images.relatable_type'
            ]);

            $this->applyFilters($query, $filters);

            $cashImages = $query->get();

            $statistics = [
                'total_cash_records' => $cashImages->count(),
                'total_cash_amount_by_currency' => [],
                'booking_related_count' => 0,
                'multiple_booking_connections' => 0,
                'single_booking_connections' => 0,
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


    public function getCashImageInternal(Request $request)
    {
        try {
            // Validate request
            $validator = Validator(
                $request->all(),
                [
                    'date' => 'nullable|string',
                    'data_verify' => 'nullable|in:0,1,true,false',
                    'bank_verify' => 'nullable|in:0,1,true,false',
                    'limit' => 'nullable|integer|min:1|max:' . self::MAX_PER_PAGE,
                    'page' => 'nullable|integer|min:1',
                ]
            );

            if ($validator->fails()) {
                throw new InvalidArgumentException($validator->errors()->first());
            }

            if ($request->date) {
                $this->validateDateFormat($request->date);
            }

            $limit = min((int) $request->get('limit', self::PER_PAGE), self::MAX_PER_PAGE);

            // Step 1: Get MMK cash images with Booking relatable_type
            $query = CashImage::select([
                'id', 'date', 'sender', 'receiver', 'amount', 'interact_bank',
                'currency', 'internal_transfer', 'data_verify','bank_verify', 'image',
                'relatable_id', 'relatable_type', 'created_at', 'updated_at'
            ])
            ->where('currency', 'MMK')
            ->where('relatable_type', 'App\Models\Booking')
            ->where('data_verify', 1);

            // Apply date filter if provided
            if ($request->filled('date')) {
                $this->applyDateFilter($query, $request->date);
            }

            // Load relationships
            $query->with([
                'relatable' => function ($q) {
                    $q->select([
                        'id', 'crm_id', 'customer_id', 'grand_total', 'sub_total',
                        'discount', 'payment_method', 'payment_status', 'bank_name',
                        'start_date', 'end_date', 'booking_date', 'created_at'
                    ])
                    ->with(['customer:id,name,phone_number,email']);
                },
                'cashBookings' => function ($q) {
                    $q->select([
                        'bookings.id', 'bookings.crm_id', 'bookings.customer_id',
                        'bookings.grand_total', 'bookings.sub_total',
                        'bookings.discount', 'bookings.payment_method', 'bookings.payment_status',
                        'bookings.bank_name', 'bookings.start_date', 'bookings.end_date',
                        'bookings.booking_date', 'bookings.created_at'
                    ])
                    ->with(['customer:id,name,phone_number,email']);
                },
                'internalTransfers'
            ]);

            $query->orderBy('date', 'desc')->orderBy('created_at', 'desc');
            $data = $query->paginate($limit);

            // Step 2: Process each cash image and get all related booking cash images
            $transformedData = $data->getCollection()->map(function ($cashImage) {
                // Get the booking
                $booking = null;
                $bookingId = null;

                if ($cashImage->relatable_id > 0 && $cashImage->relatable) {
                    $booking = $cashImage->relatable;
                    $bookingId = $booking->id;
                } elseif ($cashImage->relatable_id == 0 && $cashImage->cashBookings && $cashImage->cashBookings->count() > 0) {
                    $booking = $cashImage->cashBookings->first();
                    $bookingId = $booking->id;
                }

                // Step 3: Get ALL cash images related to this booking
                $allBookingCashImages = collect();
                if ($bookingId) {
                    $allBookingCashImages = CashImage::where(function($q) use ($bookingId) {
                        // Polymorphic relationship
                        $q->where('relatable_type', 'App\Models\Booking')
                          ->where('relatable_id', $bookingId);
                    })
                    ->orWhereHas('cashBookings', function($q) use ($bookingId) {
                        // Many-to-many relationship
                        $q->where('bookings.id', $bookingId);
                    })
                    ->with('internalTransfers')
                    ->get();
                }

                // Step 4: Analyze internal transfer status
                $internalTransferAnalysis = $this->analyzeInternalTransfers($allBookingCashImages, $cashImage->id);

                return [
                    'cash_image' => [
                        'id' => $cashImage->id,
                        'date' => $cashImage->date,
                        'sender' => $cashImage->sender,
                        'receiver' => $cashImage->receiver,
                        'amount' => $cashImage->amount,
                        'interact_bank' => $cashImage->interact_bank,
                        'currency' => $cashImage->currency,
                        'internal_transfer' => $cashImage->internal_transfer,
                        'data_verify' => $cashImage->data_verify,
                        'bank_verify' => $cashImage->bank_verify,
                        'image' => $cashImage->image,
                        'relatable_id' => $cashImage->relatable_id,
                        'relatable_type' => $cashImage->relatable_type,
                        'created_at' => $cashImage->created_at,
                        'updated_at' => $cashImage->updated_at,
                    ],
                    'booking' => $booking ? [
                        'id' => $booking->id,
                        'crm_id' => $booking->crm_id,
                        'grand_total' => $booking->grand_total,
                        'sub_total' => $booking->sub_total,
                        'discount' => $booking->discount,
                        'payment_method' => $booking->payment_method,
                        'payment_status' => $booking->payment_status,
                        'bank_name' => $booking->bank_name,
                        'start_date' => $booking->start_date,
                        'end_date' => $booking->end_date,
                        'booking_date' => $booking->booking_date,
                        'created_at' => $booking->created_at,
                        'customer' => $booking->customer ? [
                            'id' => $booking->customer->id,
                            'name' => $booking->customer->name,
                            'phone_number' => $booking->customer->phone_number,
                            'email' => $booking->customer->email ?? null,
                        ] : null,
                    ] : null,
                    'all_booking_cash_images' => $allBookingCashImages->map(function($img) {
                        // Get internal transfer info for this cash image
                        $transferInfo = $this->getInternalTransferInfo($img);

                        return [
                            'id' => $img->id,
                            'date' => $img->date,
                            'sender' => $img->sender,
                            'receiver' => $img->receiver,
                            'amount' => $img->amount,
                            'currency' => $img->currency,
                            'interact_bank' => $img->interact_bank,
                            'internal_transfer' => $img->internal_transfer,
                            'image' => $img->image,
                            'has_internal_transfer_record' => $img->internalTransfers && $img->internalTransfers->count() > 0,
                            'internal_transfer_info' => $transferInfo,
                        ];
                    })->toArray(),
                    'internal_transfer_analysis' => $internalTransferAnalysis,
                ];
            });

            $data->setCollection($transformedData);

            return [
                'success' => true,
                'data' => [
                    'items' => $transformedData,
                    'pagination' => [
                        'current_page' => $data->currentPage(),
                        'per_page' => $data->perPage(),
                        'total' => $data->total(),
                        'last_page' => $data->lastPage(),
                        'from' => $data->firstItem(),
                        'to' => $data->lastItem(),
                    ],
                    'links' => [
                        'first' => $data->url(1),
                        'last' => $data->url($data->lastPage()),
                        'prev' => $data->previousPageUrl(),
                        'next' => $data->nextPageUrl(),
                    ],
                ],
                'message' => 'Cash images with MMK currency and Booking type retrieved successfully'
            ];

        } catch (InvalidArgumentException $e) {
            Log::error('Get Cash Image Internal Validation Error: ' . $e->getMessage());

            return [
                'success' => false,
                'data' => null,
                'message' => 'Validation Error: ' . $e->getMessage(),
                'error_type' => 'validation'
            ];
        } catch (Exception $e) {
            Log::error('Get Cash Image Internal Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return [
                'success' => false,
                'data' => null,
                'message' => 'An error occurred while retrieving cash images. Error: ' . $e->getMessage(),
                'error_type' => 'system'
            ];
        }
    }

    /**
     * Get internal transfer information for a cash image
     */
    private function getInternalTransferInfo($cashImage)
    {
        if (!$cashImage->internal_transfer || !$cashImage->internalTransfers || $cashImage->internalTransfers->count() === 0) {
            return null;
        }

        $transfersInfo = [];

        foreach ($cashImage->internalTransfers as $transfer) {
            // Get direction from pivot table
            $direction = $transfer->pivot->direction ?? null;

            $transfersInfo[] = [
                'internal_transfer_id' => $transfer->id,
                'direction' => $direction,
                'rate' => $transfer->rate ?? null,
                'notes' => $transfer->notes ?? null,
                'status' => $transfer->status ?? 'active',
                'created_at' => $transfer->created_at,
            ];
        }

        return $transfersInfo;
    }

    /**
     * Analyze internal transfers for booking cash images
     */
    private function analyzeInternalTransfers($allCashImages, $currentCashImageId)
    {
        $analysis = [
            'current_cash_image_id' => $currentCashImageId,
            'total_cash_images' => $allCashImages->count(),
            'images_with_internal_transfer_true' => 0,
            'images_with_internal_transfer_false' => 0,
            'images_needing_transfer_setup' => [],
            'existing_transfers' => [],
            'transfer_suggestions' => []
        ];

        $imagesWithTransferTrue = $allCashImages->where('internal_transfer', true);
        $imagesWithTransferFalse = $allCashImages->where('internal_transfer', false);

        $analysis['images_with_internal_transfer_true'] = $imagesWithTransferTrue->count();
        $analysis['images_with_internal_transfer_false'] = $imagesWithTransferFalse->count();

        // Process images with internal_transfer = true
        foreach ($imagesWithTransferTrue as $image) {
            if ($image->internalTransfers && $image->internalTransfers->count() > 0) {
                foreach ($image->internalTransfers as $transfer) {
                    // Get direction from pivot table
                    $direction = $transfer->pivot->direction ?? null;

                    // Get FROM cash image IDs for this transfer
                    $fromImageIds = DB::table('internal_transfer_cash_images')
                        ->where('internal_transfer_id', $transfer->id)
                        ->where('direction', 'from')
                        ->pluck('cash_image_id')
                        ->toArray();

                    // Get TO cash image IDs for this transfer
                    $toImageIds = DB::table('internal_transfer_cash_images')
                        ->where('internal_transfer_id', $transfer->id)
                        ->where('direction', 'to')
                        ->pluck('cash_image_id')
                        ->toArray();

                    $analysis['existing_transfers'][] = [
                        'cash_image_id' => $image->id,
                        'internal_transfer_id' => $transfer->id,
                        'direction' => $direction,
                        'from_cash_image_ids' => $fromImageIds,
                        'to_cash_image_ids' => $toImageIds,
                        'rate' => $transfer->rate ?? null,
                        'notes' => $transfer->notes ?? null,
                        'status' => $transfer->status ?? 'active',
                        'created_at' => $transfer->created_at,
                    ];
                }
            }
        }

        // Check images with internal_transfer = false
        foreach ($imagesWithTransferFalse as $image) {
            $hasTransferRecord = $image->internalTransfers && $image->internalTransfers->count() > 0;

            if ($hasTransferRecord) {
                // Has transfer records - list them
                foreach ($image->internalTransfers as $transfer) {
                    // Get direction from pivot table
                    $direction = $transfer->pivot->direction ?? null;

                    // Get FROM cash image IDs for this transfer
                    $fromImageIds = DB::table('internal_transfer_cash_images')
                        ->where('internal_transfer_id', $transfer->id)
                        ->where('direction', 'from')
                        ->pluck('cash_image_id')
                        ->toArray();

                    // Get TO cash image IDs for this transfer
                    $toImageIds = DB::table('internal_transfer_cash_images')
                        ->where('internal_transfer_id', $transfer->id)
                        ->where('direction', 'to')
                        ->pluck('cash_image_id')
                        ->toArray();

                    $analysis['existing_transfers'][] = [
                        'cash_image_id' => $image->id,
                        'internal_transfer_id' => $transfer->id,
                        'direction' => $direction,
                        'from_cash_image_ids' => $fromImageIds,
                        'to_cash_image_ids' => $toImageIds,
                        'rate' => $transfer->rate ?? null,
                        'notes' => $transfer->notes ?? null,
                        'status' => $transfer->status ?? 'active',
                        'action_needed' => 'update', // Should update, not create
                    ];
                }
            } else {
                // No transfer records - needs to be set up
                $analysis['images_needing_transfer_setup'][] = [
                    'cash_image_id' => $image->id,
                    'amount' => $image->amount,
                    'currency' => $image->currency,
                    'date' => $image->date,
                    'sender' => $image->sender,
                    'receiver' => $image->receiver,
                    'interact_bank' => $image->interact_bank,
                    'action_needed' => 'create', // Should create new transfer
                ];
            }
        }

        // Generate transfer suggestions
        if (count($analysis['images_needing_transfer_setup']) > 1) {
            // Suggest possible from/to pairs
            $needingSetup = $analysis['images_needing_transfer_setup'];

            for ($i = 0; $i < count($needingSetup); $i++) {
                for ($j = $i + 1; $j < count($needingSetup); $j++) {
                    $analysis['transfer_suggestions'][] = [
                        'from_cash_image_id' => $needingSetup[$i]['cash_image_id'],
                        'from_amount' => $needingSetup[$i]['amount'],
                        'from_currency' => $needingSetup[$i]['currency'],
                        'from_bank' => $needingSetup[$i]['interact_bank'],
                        'to_cash_image_id' => $needingSetup[$j]['cash_image_id'],
                        'to_amount' => $needingSetup[$j]['amount'],
                        'to_currency' => $needingSetup[$j]['currency'],
                        'to_bank' => $needingSetup[$j]['interact_bank'],
                        'amounts_match' => $needingSetup[$i]['amount'] == $needingSetup[$j]['amount'],
                        'currencies_different' => $needingSetup[$i]['currency'] != $needingSetup[$j]['currency'],
                    ];
                }
            }
        }

        return $analysis;
    }
}
