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
                    'bookingItems',
                    'cashImages',
                ])
                ->where('product_type', (new BookingItemGroupService)->getModelBy($request->product_type))
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
                            $q->where(function ($subQuery) {
                                // Case 1: is_driver_collect = 1 AND has valid extra_collect_amount
                                $subQuery->where('is_driver_collect', NULL);
                            });
                        });
                    }
                })
                ->when($request->assigned, function ($query) use ($request) {
                    if ($request->assigned == 'not_have' && $request->product_type == 'private_van_tour') {
                        // Groups that have booking items with reservationCarInfo where supplier_id AND driver_id are null
                        $query->whereHas('bookingItems.reservationCarInfo', function ($q) {
                            $q->whereNull('supplier_id')
                              ->whereNull('driver_id');
                        });
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

            if ($request->booking_daterange && $request->product_type === 'private_van_tour') {
                $dates = explode(',', $request->booking_daterange);
                $dates = array_map('trim', $dates);

                if (count($dates) == 1 || (count($dates) == 2 && $dates[0] == $dates[1])) {
                    $exactDate = $dates[0];

                    $main_query->whereHas('bookingItems', function ($query) use ($exactDate) {
                        $query->where('service_date', $exactDate);
                    });
                } else {
                    $main_query->whereHas('bookingItems', function ($query) use ($dates) {
                        $query->whereBetween('service_date', $dates);
                    });
                }
            } elseif ($request->booking_daterange) {
                $dates = explode(',', $request->booking_daterange);
                $dates = array_map('trim', $dates);

                $main_query->whereHas('bookingItems', function ($query) use ($dates) {
                    $query->whereBetween('service_date', $dates);
                });
            }

            if (!in_array(Auth::user()->role, ['super_admin', 'reservation', 'auditor'])) {
                $main_query->whereHas('booking', function ($query) {
                    $query->where('created_by', Auth::id())
                        ->orWhere('past_user_id', Auth::id());
                });
            }

            $groups = $main_query->latest()->paginate($request->get('per_page', 5));

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
}
