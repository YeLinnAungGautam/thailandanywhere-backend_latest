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
        $request->validate((['product_type' => 'required|in:attraction,hotel,private_van_tour']));

        try {
            $main_query = BookingItemGroup::query()
                ->has('bookingItems')
                ->with([
                    'booking',
                    'bookingItems', // Ensure bookingItems are loaded for the resource
                    'cashImages',
                    'taxReceipts'
                ])
                ->where('booking_item_groups.product_type', (new BookingItemGroupService)->getModelBy($request->product_type))
                ->when($request->crm_id, function ($query) use ($request) {
                    $query->whereHas('booking', function ($q) use ($request) {
                        $q->where('crm_id', $request->crm_id);
                    });
                })
                ->when($request->product_name, function ($query) use ($request) {
                    $query->whereIn('id', function ($q) use ($request) {
                        $q->select('group_id')
                            ->from('booking_items')
                            ->whereIn('product_id', function ($subQuery) use ($request) {
                                if ($request->product_type == 'attraction') {
                                    $subQuery->select('id')
                                        ->from('entrance_tickets')
                                        ->where('name', 'like', '%' . $request->product_name . '%');
                                } elseif ($request->product_type == 'hotel') {
                                    $subQuery->select('id')
                                        ->from('hotels')
                                        ->where('name', 'like', '%' . $request->product_name . '%');
                                } elseif ($request->product_type == 'private_van_tour') {
                                    $subQuery->select('id')
                                        ->from('private_van_tours')
                                        ->where('name', 'like', '%' . $request->product_name . '%');
                                }
                            });
                    });
                })
                ->when($request->invoice_status, function ($query) use ($request) {
                    if ($request->invoice_status == 'not_receive') {
                        $query->whereDoesntHave('customerDocuments', function ($q) {
                            $q->where('type', 'booking_confirm_letter');
                        });
                    } else {
                        $query->whereHas('customerDocuments', function ($q) {
                            $q->where('type', 'booking_confirm_letter');
                        });
                    }
                })
                ->when($request->vantour_payment_details, function ($query) use ($request) {
                    if ($request->vantour_payment_details == 'not_have' && $request->product_type == 'private_van_tour') {
                        $query->whereHas('bookingItems', function ($q) {
                            $q->whereNull('is_driver_collect');
                        });
                    }
                })
                ->when($request->assigned, function ($query) use ($request) {
                    if ($request->assigned == 'not_have' && $request->product_type == 'private_van_tour') {
                        $query->whereHas('bookingItems.reservationCarInfo', function ($q) {
                            $q->whereNull('supplier_id')
                              ->whereNull('driver_id');
                        });
                    }
                })
                ->when($request->findTaxReceipt, function ($query) use ($request) {
                    if ($request->findTaxReceipt == "not_have_tax") {
                        $query->whereDoesntHave('taxReceipts');
                    }
                    if ($request->findTaxReceipt == "have_tax") {
                        $query->whereHas('taxReceipts');
                    }
                })
                ->when($request->expense_item_status, function ($query) use ($request) {
                    $query->whereIn('id', function ($q) use ($request) {
                        $q->select('group_id')
                            ->from('booking_items')
                            ->where('payment_status', $request->expense_item_status);
                    });
                })
                ->when($request->customer_name, function ($query) use ($request) {
                    $query->whereHas('booking.customer', function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->customer_name . '%');
                    });
                })
                ->when($request->user_id, function ($query) use ($request) {
                    $query->whereHas('booking', function ($q) use ($request) {
                        $q->where('created_by', $request->user_id)
                            ->orWhere('past_user_id', $request->user_id);
                    });
                })
                ->when($request->payment_status, function ($query) use ($request) {
                    $query->whereHas('booking', function ($q) use ($request) {
                        $q->where('payment_status', $request->payment_status);
                    });
                });

            // Date Range Filtering
            if ($request->booking_daterange) {
                $dates = explode(',', $request->booking_daterange);
                $dates = array_map('trim', $dates);

                $main_query->whereHas('bookingItems', function ($query) use ($dates, $request) {
                    if ($request->product_type === 'private_van_tour' && count($dates) == 1) {
                        $query->where('service_date', $dates[0]);
                    } else {
                        $query->whereBetween('service_date', $dates);
                    }
                });
            }

            // Sorting Logic
            if ($request->sorting) {
                $sorting = $request->sorting === 'asc' ? 'asc' : 'desc';

                if ($request->sorting_type == 'product_name') {
                    // Use a subquery to get the product name for sorting for each group.
                    // We use MIN() to pick one consistent name if a group has multiple booking items,
                    // aligning with the `first()` concept in the resource.
                    $main_query->joinSub(
                        DB::table('booking_items as bi_sort')
                            ->select(
                                'bi_sort.group_id',
                                DB::raw('MIN(CASE
                                    WHEN bi_sort.product_type = "App\\\\Models\\\\Hotel" THEN hotels_sort.name
                                    WHEN bi_sort.product_type = "App\\\\Models\\\\PrivateVanTour" THEN private_van_tours_sort.name
                                    WHEN bi_sort.product_type = "App\\\\Models\\\\GroupTour" THEN group_tours_sort.name
                                    WHEN bi_sort.product_type = "App\\\\Models\\\\EntranceTicket" THEN entrance_tickets_sort.name
                                    WHEN bi_sort.product_type = "App\\\\Models\\\\Airline" THEN airlines_sort.name
                                    ELSE "ZZZ" -- Placeholder to ensure non-matching types sort consistently (e.g., at the end)
                                END) as sort_product_name')
                            )
                            ->leftJoin('hotels as hotels_sort', function($join) {
                                $join->on('bi_sort.product_id', '=', 'hotels_sort.id')
                                    ->where('bi_sort.product_type', 'App\Models\Hotel');
                            })
                            ->leftJoin('private_van_tours as private_van_tours_sort', function($join) {
                                $join->on('bi_sort.product_id', '=', 'private_van_tours_sort.id')
                                    ->where('bi_sort.product_type', 'App\Models\PrivateVanTour');
                            })
                            ->leftJoin('group_tours as group_tours_sort', function($join) {
                                $join->on('bi_sort.product_id', '=', 'group_tours_sort.id')
                                    ->where('bi_sort.product_type', 'App\Models\GroupTour');
                            })
                            ->leftJoin('entrance_tickets as entrance_tickets_sort', function($join) {
                                $join->on('bi_sort.product_id', '=', 'entrance_tickets_sort.id')
                                    ->where('bi_sort.product_type', 'App\Models\EntranceTicket');
                            })
                            ->leftJoin('airlines as airlines_sort', function($join) {
                                $join->on('bi_sort.product_id', '=', 'airlines_sort.id')
                                    ->where('bi_sort.product_type', 'App\Models\Airline');
                            })
                            ->groupBy('bi_sort.group_id'), // Group by group_id in the subquery
                        'product_names_for_sorting', // Alias for the subquery
                        function($join) {
                            $join->on('booking_item_groups.id', '=', 'product_names_for_sorting.group_id');
                        }
                    )->orderBy('product_names_for_sorting.sort_product_name', $sorting);
                } else {
                    // Sort by service date (earliest_service_date)
                    $main_query->joinSub(
                        DB::table('booking_items')
                            ->select('group_id', DB::raw('MIN(service_date) as earliest_service_date'))
                            ->groupBy('group_id'),
                        'earliest_service_dates',
                        function($join) {
                            $join->on('booking_item_groups.id', '=', 'earliest_service_dates.group_id');
                        }
                    )->orderBy('earliest_service_dates.earliest_service_date', $sorting);
                }
            } else {
                // Default sorting if no specific sorting is requested
                $main_query->latest(); // This will order by 'created_at' DESC
            }


            // User role-based filtering
            if (!in_array(Auth::user()->role, ['super_admin', 'reservation', 'auditor'])) {
                $main_query->whereHas('booking', function ($query) {
                    $query->where('created_by', Auth::id())
                        ->orWhere('past_user_id', Auth::id());
                });
            }

            $groups = $main_query->paginate($request->get('per_page', 5));

            return $this->success(BookingItemGroupListResource::collection($groups)
                ->additional([
                    'meta' => [
                        'total_page' => (int)ceil($groups->total() / $groups->perPage()),
                    ],
                ])
                ->response()
                ->getData(), 'Group List');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
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

    public function update(Request $request,BookingItemGroup $booking_item_group){
        try {
            $data = [];
            if($request->sent_booking_request){
                $data['sent_booking_request'] = $request->sent_booking_request;
            }
            if($request->sent_expense_mail){
                $data['sent_expense_mail'] = $request->sent_expense_mail;
            }
            if($request->expense_method){
                $data['expense_method'] = $request->expense_method;
            }
            if($request->expense_status){
                $data['expense_status'] = $request->expense_status;
            }
            if($request->expense_bank_name){
                $data['expense_bank_name'] = $request->expense_bank_name;
            }
            if($request->expense_bank_account){
                $data['expense_bank_account'] = $request->expense_bank_account;
            }
            if($request->expense_total_amount){
                $data['expense_total_amount'] = $request->expense_total_amount;
            }
            if($request->confirmation_status){
                $data['confirmation_status'] = $request->confirmation_status;
            }
            if($request->confirmation_code){
                $data['confirmation_code'] = $request->confirmation_code;
            }

            $booking_item_group->update($data);
            return $this->success(new BookingItemGroupDetailResource($booking_item_group), 'Booking Item Group Detail');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    // Alternative approach - Add this method to your controller

    private function applySorting($query, $request)
    {
        if (!$request->sorting) {
            return $query->latest();
        }

        $sorting = $request->sorting === 'asc' ? 'asc' : 'desc';

        if ($request->sort_type == 'az') {
            // Get product table name
            $productTable = $this->getProductTableName($request->product_type);

            // Add a subquery to get the first product name for each group
            $query->addSelect([
                'first_product_name' => function ($subQuery) use ($productTable) {
                    $subQuery->select($productTable . '.name')
                        ->from('booking_items')
                        ->join($productTable, 'booking_items.product_id', '=', $productTable . '.id')
                        ->whereColumn('booking_items.group_id', 'booking_item_groups.id')
                        ->orderBy('booking_items.id')
                        ->limit(1);
                }
            ])
            ->orderBy('first_product_name', $sorting);

        } else {
            // Sort by earliest service date
            $query->addSelect([
                'earliest_service_date' => function ($subQuery) {
                    $subQuery->selectRaw('MIN(service_date)')
                        ->from('booking_items')
                        ->whereColumn('booking_items.group_id', 'booking_item_groups.id');
                }
            ])
            ->orderBy('earliest_service_date', $sorting);
        }

        return $query;
    }
}
