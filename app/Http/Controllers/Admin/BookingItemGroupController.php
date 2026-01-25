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

            // Build main query with relationships
            $main_query = $buildBaseQuery()->with(['booking', 'bookingItems', 'cashImages', 'taxReceipts']);

            // Apply sorting
            $this->applySorting($main_query, $request);

            $groups = $main_query->paginate($request->get('per_page', 5));

            // Calculate statistics
            $stats = $this->calculateStatistics($buildBaseQuery, $productType, $groups->total());

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
    private function calculateStatistics($buildBaseQuery, $productType, $totalFilteredGroups)
    {
        // Total cost price sum of filtered results (Kept as is because it depends on complex filters)
        $totalCostPriceSum = DB::table('booking_items')
            ->whereIn('group_id', function($query) use ($buildBaseQuery) {
                $query->select('id')->fromSub($buildBaseQuery(), 'filtered_groups');
            })
            ->sum('total_cost_price');

        // --- Efficient Global Stats Calculation ---

        // Define dates
        $now = now();
        $today = $now->copy()->startOfDay();
        $tomorrowStart = $today->copy()->addDay();
        $next2Days = $today->copy()->addDays(2)->endOfDay();
        $next3Days = $today->copy()->addDays(3)->endOfDay();
        $next7Days = $today->copy()->addDays(7)->endOfDay();
        $next30Days = $today->copy()->addDays(30)->endOfDay();

        // 1. Fetch relevant groups for the next 30 days window (superset of all other ranges)
        // We only need groups that have items with service_date in the next 30 days.
        $groupsInDataWindow = DB::table('booking_item_groups')
            ->select([
                'booking_item_groups.id',
                'booking_item_groups.booking_id',
                'booking_item_groups.product_type',
                'booking_item_groups.expense_status',
                'booking_item_groups.sent_expense_mail',
                'booking_item_groups.sent_booking_request',
                'booking_item_groups.have_invoice_mail',
                'booking_item_groups.fill_status',
                'earliest_service_date'
            ])
            ->joinSub(
                DB::table('booking_items')
                    ->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                    ->groupBy('group_id'),
                'group_dates',
                'booking_item_groups.id',
                '=',
                'group_dates.group_id'
            )
            ->where('booking_item_groups.product_type', $productType)
            ->whereBetween('earliest_service_date', [$today, $next30Days])
            ->get();

        $groupIds = $groupsInDataWindow->pluck('id')->toArray();
        $bookingIds = $groupsInDataWindow->pluck('booking_id')->unique()->toArray();

        // 2. Fetch related Bookings (for payment status)
        $bookings = [];
        if (!empty($bookingIds)) {
            $bookings = DB::table('bookings')
                ->whereIn('id', $bookingIds)
                ->pluck('payment_status', 'id'); // id => payment_status
        }

        // 3. Fetch related Customer Documents (for counts)
        $documents = []; // group_id => [type1, type2...]
        if (!empty($groupIds)) {
            $docs = DB::table('customer_documents')
                ->select('booking_item_group_id', 'type')
                ->whereIn('booking_item_group_id', $groupIds)
                ->whereIn('type', ['passport', 'booking_confirm_letter'])
                ->get();

            foreach ($docs as $doc) {
                $documents[$doc->booking_item_group_id][] = $doc->type;
            }
        }

        // 4. Fetch unfilled checks (for fill_status calculation optimization if needed)
        // Since the original logic checked for *existence* of unfilled items for 'fill_status_not_pending', but the field is on the group 'fill_status'.
        // The original query checked `big.fill_status != 'pending'` AND `NOT NULL`.
        // It also had a separate fill check logic for `filled_next_2_days`.
        // Let's implement `filled_next_2_days` check efficiently.
        // We need to know which groups have ANY unfilled booking items.
        $unfilledGroupIds = [];
        if (!empty($groupIds)) {
            $unfilledGroupIds = DB::table('booking_items')
                ->whereIn('group_id', $groupIds)
                ->where(function($q) {
                     $q->whereNull('pickup_time')
                        ->orWhereNull('pickup_location')
                        ->orWhereNull('route_plan')
                        ->orWhereNull('contact_number')
                        ->orWhere('pickup_time', '')
                        ->orWhere('pickup_location', '')
                        ->orWhere('route_plan', '')
                        ->orWhere('contact_number', '');
                })
                ->pluck('group_id')
                ->unique()
                ->toArray();
            // Invert logic: if in this array, it is NOT fully filled.
            $unfilledMap = array_flip($unfilledGroupIds);
        }

        // 5. Fetch Unassigned Driver checks
        $unassignedGroupIds = [];
        if (!empty($groupIds) && $productType === 'App\Models\PrivateVanTour') { // Assuming standard namespace, or just check request
             // Actually productType coming in is the class name string from service.
             // We can just run this query. If other types don't have this table joined, it might be tricky?
             // The original code was: `leftJoin('reservation_car_infos...`).
             // If product type is not PrivateVanTour, this metric might not apply or be 0?
             // The original code passed $productType to countAssignedDrivers.
             // Let's assume we run it for all or check if it's PrivateVanTour.
             // To be safe and efficient, we query reservation_car_infos for these items.

             // We need groups where `NOT EXISTS ... missing driver`.
             // So we find groups that HAVE missing driver.
             $itemsWithMissingDriver = DB::table('booking_items as bi')
                 ->leftJoin('reservation_car_infos as rci', 'rci.booking_item_id', '=', 'bi.id')
                 ->whereIn('bi.group_id', $groupIds)
                 ->where(function($q) {
                     $q->whereNull('rci.id')
                        ->orWhereNull('rci.supplier_id')
                        ->orWhereNull('rci.driver_id');
                 })
                 ->pluck('bi.group_id')
                 ->unique()
                 ->toArray();
             $unassignedMap = array_flip($itemsWithMissingDriver);
        }


        // Initialize counters
        $stats = [
            'total_cost_price_sum' => $totalCostPriceSum,
            'total_filtered_groups' => $totalFilteredGroups,
            'expense_not_fully_paid' => 0,
            'expense_mail_sent' => 0,
            'customer_fully_paid' => 0,
            'passport_have_2_days' => 0,
            'fill_status_not_pending_2_days' => 0,
            'prove_booking_sent_next_30_days' => 0,
            'invoice_mail_sent_next_7_days' => 0,
            'invoice_confirmed_next_7_days' => 0,
            'expense_mail_sent_next_7_days' => 0,
            'without_confirmation_letter' => 0,
            'total_next_2_days' => 0,
            'total_next_3_days' => 0,
            'total_next_3_days_not_today' => 0,
            'total_next_7_days' => 0,
            'total_next_30_days' => 0,
            'filled_next_2_days' => 0,
            'assigned_driver_next_2_days' => 0,
        ];

        // Iterate and Count
        foreach ($groupsInDataWindow as $group) {
            $date = $group->earliest_service_date;
            $gid = $group->id;

            // Check date ranges
            $isNext2Days = $date >= $today && $date <= $next2Days;
            $isNext3Days = $date >= $today && $date <= $next3Days;
            $isNext3DaysNotToday = $date >= $tomorrowStart && $date <= $next3Days;
            $isNext7Days = $date >= $today && $date <= $next7Days;
            $isNext30Days = $date >= $today && $date <= $next30Days;
            // Note: query filtered by next30Days, so $isNext30Days is basically true if $date >= $today

            if ($isNext2Days) $stats['total_next_2_days']++;
            if ($isNext3Days) $stats['total_next_3_days']++;
            if ($isNext3DaysNotToday) $stats['total_next_3_days_not_today']++;
            if ($isNext7Days) $stats['total_next_7_days']++;
            if ($isNext30Days) $stats['total_next_30_days']++;

            // Expense Not Fully Paid (Tomorrow to Day After) -> $yearNext2Days but start from tomorrow
            if ($date >= $tomorrowStart && $date <= $next2Days) {
                if ($group->expense_status !== 'fully_paid') {
                    $stats['expense_not_fully_paid']++;
                }
            }

            // Expense Mail Sent (Today to Next 3 Days)
            if ($isNext3Days) {
                 if ($group->sent_expense_mail == 1) {
                     $stats['expense_mail_sent']++;
                 }
            }

            // Customer Fully Paid (Today to Next 2 Days)
            if ($isNext2Days) {
                $paymentStatus = $bookings[$group->booking_id] ?? null;
                if ($paymentStatus === 'fully_paid') {
                    $stats['customer_fully_paid']++;
                }
            }

            // Passport Have 2 Days
            if ($isNext2Days) {
                $groupDocs = $documents[$gid] ?? [];
                if (in_array('passport', $groupDocs)) {
                    $stats['passport_have_2_days']++;
                }
            }

            // Fill Status Not Pending 2 Days
            if ($isNext2Days) {
                if (!is_null($group->fill_status) && $group->fill_status !== 'pending') {
                    $stats['fill_status_not_pending_2_days']++;
                }
            }

            // Prove Booking Sent Next 30 Days
            if ($isNext30Days) {
                if ($group->sent_booking_request == 1) {
                    $stats['prove_booking_sent_next_30_days']++;
                }
            }

            // Invoice Mail Sent Next 7 Days
            if ($isNext7Days) {
                if ($group->have_invoice_mail == 1) {
                    $stats['invoice_mail_sent_next_7_days']++;
                }
            }

            // Invoice Confirmed Next 7 Days
            if ($isNext7Days) {
                 $groupDocs = $documents[$gid] ?? [];
                 if (in_array('booking_confirm_letter', $groupDocs)) {
                     $stats['invoice_confirmed_next_7_days']++;
                 }
            }

            // Expense Mail Sent Next 7 days
            if ($isNext7Days) {
                if ($group->sent_expense_mail == 1) {
                    $stats['expense_mail_sent_next_7_days']++;
                }
            }

            // Without Confirmation Letter (Next 7 days)
            if ($isNext7Days) {
                $groupDocs = $documents[$gid] ?? [];
                if (in_array('booking_confirm_letter', $groupDocs)) {
                    // Logic was: countWithDocument.
                    // Wait, the key is "without_confirmation_letter", but the original code calls countWithDocument('booking_confirm_letter').
                    // Original code: 'without_confirmation_letter' => $this->countWithDocument(..., 'booking_confirm_letter', ...)
                    // This counts groups WITH the document.
                    // The label 'without_confirmation_letter' seems misleading in the original code?
                    // Let's stick to REPLICATING the original logic: countWithDocument.
                    $stats['without_confirmation_letter']++;
                }
            }

            // Filled Next 2 Days
            // Logic: NotExists(unfilled items)
            if ($isNext2Days) {
                if (!isset($unfilledMap[$gid])) {
                    $stats['filled_next_2_days']++;
                }
            }

            // Assigned Driver Next 2 Days
            // Logic: NotExists(unassigned items)
            if ($isNext2Days) {
                if (!isset($unassignedMap[$gid])) {
                    $stats['assigned_driver_next_2_days']++;
                }
            }
        }

        return $stats;
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
