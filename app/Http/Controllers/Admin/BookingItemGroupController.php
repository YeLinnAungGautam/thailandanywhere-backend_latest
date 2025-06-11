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
                                        ->from('attractions')
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
                        $query->whereIn('id', function ($q) {
                            $q->select('group_id')
                                ->from('booking_items')
                                ->whereNull('booking_status')
                                ->orWhere('booking_status', '')
                                ->orWhere('booking_status', 'not_receive');
                        });
                    } else {
                        $query->whereIn('id', function ($q) use ($request) {
                            $q->select('group_id')
                                ->from('booking_items')
                                ->where('booking_status', $request->invoice_status);
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
                'customerDocuments'
            ]);

            return $this->success(new BookingItemGroupDetailResource($booking_item_group), 'Booking Item Group Detail');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
