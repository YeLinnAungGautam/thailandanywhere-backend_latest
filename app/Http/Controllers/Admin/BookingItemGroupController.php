<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItem\BookingItemGroupDetailResource;
use App\Http\Resources\BookingItem\BookingItemGroupListResource;
use App\Models\BookingItemGroup;
use App\Services\API\BookingItemGroupService;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingItemGroupController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $request->validate(['product_type' => 'required|in:attraction,hotel,private_van_tour']);

        try {
            $productType = (new BookingItemGroupService)->getModelBy($request->product_type);

            // Create base query function to avoid code duplication
            $buildBaseQuery = function() use ($request, $productType) {
                return BookingItemGroup::query()
                    ->has('bookingItems')
                    ->where('booking_item_groups.product_type', $productType)
                    ->when($request->crm_id, function ($query) use ($request) {
                        $query->whereHas('booking', fn($q) => $q->where('crm_id', $request->crm_id));
                    })
                    ->when($request->product_name, function ($query) use ($request) {
                        $query->whereIn('id', function ($q) use ($request) {
                            $q->select('group_id')
                                ->from('booking_items')
                                ->whereIn('product_id', function ($subQuery) use ($request) {
                                    $table = match($request->product_type) {
                                        'attraction' => 'entrance_tickets',
                                        'hotel' => 'hotels',
                                        'private_van_tour' => 'private_van_tours',
                                    };
                                    $subQuery->select('id')
                                        ->from($table)
                                        ->where('name', 'like', '%' . $request->product_name . '%');
                                });
                        });
                    })
                    ->when($request->is_allowment_have, function ($query) {
                        $query->whereHas('bookingItems', fn($q) => $q->where('is_allowment_have', 1));
                    })
                    ->when($request->sent_booking_request, function ($q) use ($request) {
                        $q->where('sent_booking_request', $request->sent_booking_request === 'sent' ? 1 : 0);
                    })
                    ->when($request->booking_request_proof, function ($query) use ($request) {
                        $hasProof = $request->booking_request_proof === 'proved';
                        $query->{$hasProof ? 'whereHas' : 'whereDoesntHave'}('customerDocuments', function ($q) {
                            $q->where('type', 'booking_request_proof');
                        });
                    })
                    ->when($request->sent_expense_mail, function ($q) use ($request) {
                        $q->where('sent_expense_mail', $request->sent_expense_mail === 'sent' ? 1 : 0);
                    })
                    ->when($request->over_expense_amount, function ($q) use ($request) {
                        $q->where('total_cost_price', '>', $request->over_expense_amount);
                    })
                    ->when($request->expense_mail_proof, function ($query) use ($request) {
                        $hasProof = $request->expense_mail_proof === 'proved';
                        $query->{$hasProof ? 'whereHas' : 'whereDoesntHave'}('customerDocuments', function ($q) {
                            $q->where('type', 'expense_mail_proof');
                        });
                    })
                    ->when($request->have_invoice_mail, function ($q) use ($request) {
                        $q->where('have_invoice_mail', $request->have_invoice_mail === 'sent' ? 1 : 0);
                    })
                    ->when($request->invoice_mail_proof, function ($query) use ($request) {
                        $hasProof = $request->invoice_mail_proof === 'proved';
                        $query->{$hasProof ? 'whereHas' : 'whereDoesntHave'}('customerDocuments', function ($q) {
                            $q->where('type', 'invoice_mail_proof');
                        });
                    })
                    ->when($request->invoice_status, function ($query) use ($request) {
                        $hasInvoice = $request->invoice_status === 'receive';
                        $query->{$hasInvoice ? 'whereHas' : 'whereDoesntHave'}('customerDocuments', function ($q) {
                            $q->where('type', 'booking_confirm_letter');
                        });
                    })
                    ->when($request->passportFilter, function ($query) use ($request) {
                        $hasPassport = $request->passportFilter === 'have';
                        $query->{$hasPassport ? 'whereHas' : 'whereDoesntHave'}('customerDocuments', function ($q) {
                            $q->where('type', 'passport');
                        });
                    })
                    ->when($request->commentFilter, function ($query) use ($request) {
                        if ($request->commentFilter === 'pending') {
                            $query->where(fn($q) => $q->whereNull('fill_status')->orWhere('fill_status', 'pending'));
                        } else {
                            $query->where('fill_status', $request->commentFilter);
                        }
                    })
                    ->when($request->vantour_payment_details === 'not_have' && $request->product_type === 'private_van_tour', function ($query) {
                        $query->whereHas('bookingItems', fn($q) => $q->whereNull('is_driver_collect'));
                    })
                    // OPTIMIZED: Fixed the performance issue
                    ->when($request->assigned, function ($query) use ($request) {
                        if ($request->assigned == 'not_have' && $request->product_type == 'private_van_tour') {
                            $query->whereHas('bookingItems.reservationCarInfo', function ($q) {
                                $q->whereNull('supplier_id')
                                  ->whereNull('driver_id');
                            });
                        }
                    })
                    ->when($request->filled_status && $request->product_type === 'private_van_tour', function ($query) use ($request) {
                        $isUnfilled = $request->filled_status === 'unfilled';

                        if ($isUnfilled) {
                            // Has AT LEAST ONE unfilled item
                            $query->whereExists(function($q) {
                                $q->select(DB::raw(1))
                                    ->from('booking_items')
                                    ->whereColumn('booking_items.group_id', 'booking_item_groups.id')
                                    ->where(function($subQ) {
                                        $subQ->whereNull('booking_items.pickup_time')
                                            ->orWhereNull('booking_items.pickup_location')
                                            ->orWhereNull('booking_items.route_plan')
                                            ->orWhereNull('booking_items.contact_number')
                                            ->orWhere('booking_items.pickup_time', '')
                                            ->orWhere('booking_items.pickup_location', '')
                                            ->orWhere('booking_items.route_plan', '')
                                            ->orWhere('booking_items.contact_number', '');
                                    });
                            });
                        } else {
                            // ALL items must be filled
                            $query->whereNotExists(function($q) {
                                $q->select(DB::raw(1))
                                    ->from('booking_items')
                                    ->whereColumn('booking_items.group_id', 'booking_item_groups.id')
                                    ->where(function($subQ) {
                                        $subQ->whereNull('booking_items.pickup_time')
                                            ->orWhereNull('booking_items.pickup_location')
                                            ->orWhereNull('booking_items.route_plan')
                                            ->orWhereNull('booking_items.contact_number')
                                            ->orWhere('booking_items.pickup_time', '')
                                            ->orWhere('booking_items.pickup_location', '')
                                            ->orWhere('booking_items.route_plan', '')
                                            ->orWhere('booking_items.contact_number', '');
                                    });
                            })
                            ->whereExists(function($q) {
                                $q->select(DB::raw(1))
                                    ->from('booking_items')
                                    ->whereColumn('booking_items.group_id', 'booking_item_groups.id');
                            });
                        }
                    })
                    ->when($request->findTaxReceipt, function ($query) use ($request) {
                        $hasTax = $request->findTaxReceipt === 'have_tax';
                        $query->{$hasTax ? 'whereHas' : 'whereDoesntHave'}('taxReceipts');
                    })
                    ->when($request->expense_item_status, function ($query) use ($request) {

                        if ($request->expense_item_status === 'not_fully_paid') {
                            $query->whereIn('id', function ($q) {
                                $q->select('group_id')
                                    ->from('booking_items')
                                    ->where(function($subQ) {
                                        $subQ->where('payment_status', 'not_paid')
                                            ->orWhere('payment_status', 'partially_paid')
                                            ->orWhere('payment_status', '')
                                            ->orWhereNull('payment_status');
                                    });
                            });
                        } else {
                            $query->whereIn('id', function ($q) use ($request) {
                                $q->select('group_id')
                                    ->from('booking_items')
                                    ->where('payment_status', $request->expense_item_status);
                            });
                        }
                    })
                    ->when($request->customer_name, function ($query) use ($request) {
                        $query->whereHas('booking.customer', fn($q) => $q->where('name', 'like', '%' . $request->customer_name . '%'));
                    })
                    ->when($request->user_id, function ($query) use ($request) {
                        $query->whereHas('booking', function ($q) use ($request) {
                            $q->where('created_by', $request->user_id)
                                ->orWhere('past_user_id', $request->user_id);
                        });
                    })
                    ->when($request->payment_status, function ($query) use ($request) {
                        if ($request->payment_status === 'not_fully_paid') {
                            // Not fully paid means any status except 'fully_paid'
                            $query->whereHas('booking', function($q) {
                                $q->where(function($subQ) {
                                    $subQ->where('payment_status', '!=', 'fully_paid')
                                        ->orWhereNull('payment_status');
                                });
                            });
                        } else {
                            // For specific statuses
                            $query->whereHas('booking', fn($q) => $q->where('payment_status', $request->payment_status));
                        }
                    })
                    ->when($request->booking_daterange, function ($query) use ($request) {
                        $dates = array_map('trim', explode(',', $request->booking_daterange));

                        $query->whereIn('id', function ($q) use ($dates, $request) {
                            $subQuery = DB::table('booking_items')
                                ->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                                ->groupBy('group_id');

                            if ($request->product_type === 'private_van_tour' && count($dates) === 1) {
                                $subQuery->havingRaw('MIN(service_date) = ?', [$dates[0]]);
                            } else {
                                $subQuery->havingRaw('MIN(service_date) BETWEEN ? AND ?', $dates);
                            }

                            $q->select('group_id')->fromSub($subQuery, 'filtered_dates');
                        });
                    })
                    ->when($request->deadline_date && $request->deadline_days, function ($query) use ($request) {
                        $query->whereHas('bookingItems', function ($q) use ($request) {
                            $q->whereRaw('DATE_SUB(service_date, INTERVAL ? DAY) = ?', [
                                $request->deadline_days,
                                $request->deadline_date
                            ]);
                        });
                    })
                    ->when(!in_array(Auth::user()->role, ['super_admin', 'reservation', 'auditor']), function ($query) {
                        $query->whereHas('booking', fn($q) => $q->where('created_by', Auth::id())->orWhere('past_user_id', Auth::id()));
                    });
            };

            // Calculate statistics
            $stats = $this->calculateStatistics($buildBaseQuery, $productType);

            // Build main query with relationships
            $main_query = $buildBaseQuery()->with(['booking', 'bookingItems', 'cashImages', 'taxReceipts']);

            // Apply sorting
            $this->applySorting($main_query, $request);

            $groups = $main_query->paginate($request->get('per_page', 5));

            return $this->success(
                BookingItemGroupListResource::collection($groups)
                    ->additional(['meta' => array_merge($stats, ['total_page' => (int)ceil($groups->total() / $groups->perPage())])])
                    ->response()
                    ->getData(),
                'Group List'
            );
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    // ... rest of your methods remain the same
    private function calculateStatistics($buildBaseQuery, $productType)
    {
        // Total cost price sum of filtered results
        $totalCostPriceSum = DB::table('booking_items')
            ->whereIn('group_id', function($query) use ($buildBaseQuery) {
                $query->select('id')->fromSub($buildBaseQuery(), 'filtered_groups');
            })
            ->sum('total_cost_price');

        // Define common date ranges
        $today = now()->startOfDay();
        $next2Days = now()->addDays(2)->endOfDay();
        $next3Days = now()->addDays(3)->endOfDay();
        $next7Days = now()->addDays(7)->endOfDay();
        $next30Days = now()->addDays(30)->endOfDay();
        $tomorrowStart = now()->addDays(1)->startOfDay();

        // Helper function to count groups by date range (using MIN service_date)
        $countGroupsByDateRange = function($dateStart, $dateEnd) use ($productType) {
            return DB::table('booking_item_groups')
                ->where('product_type', $productType)
                ->whereExists(function($query) use ($dateStart, $dateEnd) {
                    $query->select(DB::raw(1))
                        ->from(function($subQuery) {
                            $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                                ->from('booking_items')
                                ->groupBy('group_id');
                        }, 'earliest_dates')
                        ->whereColumn('earliest_dates.group_id', 'booking_item_groups.id')
                        ->whereBetween('earliest_dates.earliest_service_date', [$dateStart, $dateEnd]);
                })
                ->count();
        };

        $stats = [
            'total_cost_price_sum' => $totalCostPriceSum,
            'total_filtered_groups' => $buildBaseQuery()->count(),

            // Expense related (tomorrow to day after) - using MIN service_date
            'expense_not_fully_paid' => DB::table('booking_item_groups')
                ->where('product_type', $productType)
                ->where('expense_status', '!=', 'fully_paid')
                ->whereExists(function($query) use ($tomorrowStart, $next2Days) {
                    $query->select(DB::raw(1))
                        ->from(function($subQuery) {
                            $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                                ->from('booking_items')
                                ->groupBy('group_id');
                        }, 'earliest_dates')
                        ->whereColumn('earliest_dates.group_id', 'booking_item_groups.id')
                        ->whereBetween('earliest_dates.earliest_service_date', [$tomorrowStart, $next2Days]);
                })
                ->count(),

            // Mail sent counts (today to next 3 days) - using MIN service_date
            'expense_mail_sent' => DB::table('booking_item_groups')
                ->where('product_type', $productType)
                ->where('sent_expense_mail', 1)
                ->whereExists(function($query) use ($today, $next3Days) {
                    $query->select(DB::raw(1))
                        ->from(function($subQuery) {
                            $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                                ->from('booking_items')
                                ->groupBy('group_id');
                        }, 'earliest_dates')
                        ->whereColumn('earliest_dates.group_id', 'booking_item_groups.id')
                        ->whereBetween('earliest_dates.earliest_service_date', [$today, $next3Days]);
                })
                ->count(),

            // Customer payment (today to next 2 days) - using MIN service_date
            'customer_fully_paid' => DB::table('booking_item_groups')
                ->join('bookings', 'booking_item_groups.booking_id', '=', 'bookings.id')
                ->where('booking_item_groups.product_type', $productType)
                ->where('bookings.payment_status', 'fully_paid')
                ->whereExists(function($query) use ($today, $next2Days) {
                    $query->select(DB::raw(1))
                        ->from(function($subQuery) {
                            $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                                ->from('booking_items')
                                ->groupBy('group_id');
                        }, 'earliest_dates')
                        ->whereColumn('earliest_dates.group_id', 'booking_item_groups.id')
                        ->whereBetween('earliest_dates.earliest_service_date', [$today, $next2Days]);
                })
                ->count(),

            // Document counts (next 2 days) - using MIN service_date
            'passport_have_2_days' => $this->countWithDocument($productType, 'passport', $today, $next2Days),

            'fill_status_not_pending_2_days' => DB::table('booking_item_groups as big')
                ->where('big.product_type', $productType)
                ->whereNotNull('big.fill_status')
                ->where('big.fill_status', '!=', 'pending')
                ->whereExists(function($query) use ($today, $next2Days) {
                    $query->select(DB::raw(1))
                        ->from(function($subQuery) {
                            $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                                ->from('booking_items')
                                ->groupBy('group_id');
                        }, 'earliest_dates')
                        ->whereColumn('earliest_dates.group_id', 'big.id')
                        ->whereBetween('earliest_dates.earliest_service_date', [$today, $next2Days]);
                })
                ->count(),

            // Prove Booking Sent (using MIN service_date)
            'prove_booking_sent_next_30_days' => DB::table('booking_item_groups')
                ->where('product_type', $productType)
                ->where('sent_booking_request', 1)
                ->whereExists(function($query) use ($today, $next30Days) {
                    $query->select(DB::raw(1))
                        ->from(function($subQuery) {
                            $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                                ->from('booking_items')
                                ->groupBy('group_id');
                        }, 'earliest_dates')
                        ->whereColumn('earliest_dates.group_id', 'booking_item_groups.id')
                        ->whereBetween('earliest_dates.earliest_service_date', [$today, $next30Days]);
                })
                ->count(),

            // Invoice Mail Sent (using MIN service_date)
            'invoice_mail_sent_next_7_days' => DB::table('booking_item_groups')
                ->where('product_type', $productType)
                ->where('have_invoice_mail', 1)
                ->whereExists(function($query) use ($today, $next7Days) {
                    $query->select(DB::raw(1))
                        ->from(function($subQuery) {
                            $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                                ->from('booking_items')
                                ->groupBy('group_id');
                        }, 'earliest_dates')
                        ->whereColumn('earliest_dates.group_id', 'booking_item_groups.id')
                        ->whereBetween('earliest_dates.earliest_service_date', [$today, $next7Days]);
                })
                ->count(),

            // Invoice Confirmed (using MIN service_date)
            'invoice_confirmed_next_7_days' => DB::table('booking_item_groups')
                ->where('product_type', $productType)
                ->whereExists(function($query) {
                    $query->select(DB::raw(1))
                        ->from('customer_documents')
                        ->whereColumn('customer_documents.booking_item_group_id', 'booking_item_groups.id')
                        ->where('customer_documents.type', 'booking_confirm_letter');
                })
                ->whereExists(function($query) use ($today, $next7Days) {
                    $query->select(DB::raw(1))
                        ->from(function($subQuery) {
                            $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                                ->from('booking_items')
                                ->groupBy('group_id');
                        }, 'earliest_dates')
                        ->whereColumn('earliest_dates.group_id', 'booking_item_groups.id')
                        ->whereBetween('earliest_dates.earliest_service_date', [$today, $next7Days]);
                })
                ->count(),

            // Expense Mail Sent (using MIN service_date)
            'expense_mail_sent_next_7_days' => DB::table('booking_item_groups')
                ->where('product_type', $productType)
                ->where('sent_expense_mail', 1)
                ->whereExists(function($query) use ($today, $next7Days) {
                    $query->select(DB::raw(1))
                        ->from(function($subQuery) {
                            $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                                ->from('booking_items')
                                ->groupBy('group_id');
                        }, 'earliest_dates')
                        ->whereColumn('earliest_dates.group_id', 'booking_item_groups.id')
                        ->whereBetween('earliest_dates.earliest_service_date', [$today, $next7Days]);
                })
                ->count(),

            // Confirmation letter (next 7 days)
            'without_confirmation_letter' => $this->countWithDocument($productType, 'booking_confirm_letter', $today, $next7Days),

            // Total counts for different date ranges (using MIN service_date)
            'total_next_2_days' => $countGroupsByDateRange($today, $next2Days),
            'total_next_3_days' => $countGroupsByDateRange($today, $next3Days),
            'total_next_3_days_not_today' => $countGroupsByDateRange($tomorrowStart, $next3Days),
            'total_next_7_days' => $countGroupsByDateRange($today, $next7Days),
            'total_next_30_days' => $countGroupsByDateRange($today, $next30Days),

            // Filled and Assigned counts
            'filled_next_2_days' => $this->countFilledItems($productType, $today, $next2Days),
            'assigned_driver_next_2_days' => $this->countAssignedDrivers($productType, $today, $next2Days),
        ];

        return $stats;
    }

    private function countWithDocument($productType, $documentType, $dateStart, $dateEnd)
    {
        return DB::table('booking_item_groups as big')
            ->where('big.product_type', $productType)
            ->whereExists(function($query) use ($documentType) {
                $query->select(DB::raw(1))
                    ->from('customer_documents')
                    ->whereColumn('customer_documents.booking_item_group_id', 'big.id')
                    ->where('customer_documents.type', $documentType);
            })
            ->whereExists(function($query) use ($dateStart, $dateEnd) {
                $query->select(DB::raw(1))
                    ->from(function($subQuery) {
                        $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                            ->from('booking_items')
                            ->groupBy('group_id');
                    }, 'earliest_dates')
                    ->whereColumn('earliest_dates.group_id', 'big.id')
                    ->whereBetween('earliest_dates.earliest_service_date', [$dateStart, $dateEnd]);
            })
            ->count();
    }

    private function countFilledItems($productType, $dateStart, $dateEnd)
    {
        return DB::table('booking_item_groups as big')
            ->where('big.product_type', $productType)
            ->whereNotExists(function($query) use ($dateStart, $dateEnd) {
                $query->select(DB::raw(1))
                    ->from('booking_items')
                    ->whereColumn('booking_items.group_id', 'big.id')
                    ->where(function($q) {
                        $q->whereNull('booking_items.pickup_time')
                            ->orWhereNull('booking_items.pickup_location')
                            ->orWhereNull('booking_items.route_plan')
                            ->orWhereNull('booking_items.contact_number')
                            ->orWhere('booking_items.pickup_time', '')
                            ->orWhere('booking_items.pickup_location', '')
                            ->orWhere('booking_items.route_plan', '')
                            ->orWhere('booking_items.contact_number', '');
                    });
            })
            ->whereExists(function($query) use ($dateStart, $dateEnd) {
                $query->select(DB::raw(1))
                    ->from(function($subQuery) {
                        $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                            ->from('booking_items')
                            ->groupBy('group_id');
                    }, 'earliest_dates')
                    ->whereColumn('earliest_dates.group_id', 'big.id')
                    ->whereBetween('earliest_dates.earliest_service_date', [$dateStart, $dateEnd]);
            })
            ->count();
    }

    private function countAssignedDrivers($productType, $dateStart, $dateEnd)
    {
        return DB::table('booking_item_groups as big')
            ->where('big.product_type', $productType)
            ->whereNotExists(function($query) {
                $query->select(DB::raw(1))
                    ->from('booking_items as bi')
                    ->whereColumn('bi.group_id', 'big.id')
                    ->leftJoin('reservation_car_infos as rci', 'rci.booking_item_id', '=', 'bi.id')
                    ->where(function($q) {
                        $q->whereNull('rci.id')
                            ->orWhereNull('rci.supplier_id')
                            ->orWhereNull('rci.driver_id');
                    });
            })
            ->whereExists(function($query) use ($dateStart, $dateEnd) {
                $query->select(DB::raw(1))
                    ->from(function($subQuery) {
                        $subQuery->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                            ->from('booking_items')
                            ->groupBy('group_id');
                    }, 'earliest_dates')
                    ->whereColumn('earliest_dates.group_id', 'big.id')
                    ->whereBetween('earliest_dates.earliest_service_date', [$dateStart, $dateEnd]);
            })
            ->count();
    }

    private function applySorting($query, $request)
    {
        if (!$request->sorting) {
            $query->orderBy('booking_item_groups.id', 'desc');
            return;
        }

        $sorting = $request->sorting === 'asc' ? 'asc' : 'desc';

        if ($request->sorting_type === 'product_name') {
            $query->joinSub(
                DB::table('booking_items as bi_sort')
                    ->select(
                        'bi_sort.group_id',
                        DB::raw('MIN(CASE
                            WHEN bi_sort.product_type = "App\\\\Models\\\\Hotel" THEN hotels_sort.name
                            WHEN bi_sort.product_type = "App\\\\Models\\\\PrivateVanTour" THEN private_van_tours_sort.name
                            WHEN bi_sort.product_type = "App\\\\Models\\\\GroupTour" THEN group_tours_sort.name
                            WHEN bi_sort.product_type = "App\\\\Models\\\\EntranceTicket" THEN entrance_tickets_sort.name
                            WHEN bi_sort.product_type = "App\\\\Models\\\\Airline" THEN airlines_sort.name
                            ELSE "ZZZ"
                        END) as sort_product_name')
                    )
                    ->leftJoin('hotels as hotels_sort', fn($join) =>
                        $join->on('bi_sort.product_id', '=', 'hotels_sort.id')
                            ->where('bi_sort.product_type', 'App\Models\Hotel')
                    )
                    ->leftJoin('private_van_tours as private_van_tours_sort', fn($join) =>
                        $join->on('bi_sort.product_id', '=', 'private_van_tours_sort.id')
                            ->where('bi_sort.product_type', 'App\Models\PrivateVanTour')
                    )
                    ->leftJoin('group_tours as group_tours_sort', fn($join) =>
                        $join->on('bi_sort.product_id', '=', 'group_tours_sort.id')
                            ->where('bi_sort.product_type', 'App\Models\GroupTour')
                    )
                    ->leftJoin('entrance_tickets as entrance_tickets_sort', fn($join) =>
                        $join->on('bi_sort.product_id', '=', 'entrance_tickets_sort.id')
                            ->where('bi_sort.product_type', 'App\Models\EntranceTicket')
                    )
                    ->leftJoin('airlines as airlines_sort', fn($join) =>
                        $join->on('bi_sort.product_id', '=', 'airlines_sort.id')
                            ->where('bi_sort.product_type', 'App\Models\Airline')
                    )
                    ->groupBy('bi_sort.group_id'),
                'product_names_for_sorting',
                fn($join) => $join->on('booking_item_groups.id', '=', 'product_names_for_sorting.group_id')
            )
            ->orderBy('product_names_for_sorting.sort_product_name', $sorting)
            ->orderBy('booking_item_groups.id', $sorting);
        } elseif (in_array($request->sorting_type, ['service_date', 'firstest_service_date'])) {
            $query->joinSub(
                DB::table('booking_items')
                    ->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                    ->groupBy('group_id'),
                'earliest_service_dates',
                fn($join) => $join->on('booking_item_groups.id', '=', 'earliest_service_dates.group_id')
            )
            ->orderBy('earliest_service_dates.earliest_service_date', $sorting)
            ->orderBy('booking_item_groups.id', $sorting);
        } else {
            $query->orderBy('booking_item_groups.id', $sorting);
        }
    }

    public function detail(BookingItemGroup $booking_item_group)
    {
        try {
            $booking_item_group->load([
                'booking',
                'bookingItems',
                'bookingItems.product',
                'customerDocuments',
                'cashImages',
            ]);

            return $this->success(new BookingItemGroupDetailResource($booking_item_group), 'Booking Item Group Detail');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function update(Request $request, BookingItemGroup $booking_item_group)
    {
        try {
            $allowedFields = [
                'sent_booking_request', 'sent_expense_mail', 'booking_email_sent_date',
                'expense_email_sent_date', 'expense_method', 'expense_status',
                'expense_bank_name', 'expense_bank_account', 'expense_total_amount',
                'confirmation_status', 'confirmation_code', 'have_invoice_mail',
                'invoice_mail_sent_date', 'comment_sale', 'comment_res',
                'fill_comment', 'fill_status', 'comment_reserve'
            ];

            $data = $request->only($allowedFields);

            $booking_item_group->update($data);

            return $this->success(
                new BookingItemGroupDetailResource($booking_item_group),
                'Booking Item Group Updated Successfully'
            );
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
