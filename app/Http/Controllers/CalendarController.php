<?php

namespace App\Http\Controllers;

use App\Http\Resources\CalendarResource;
use App\Models\BookingItem;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $filter = $request->query('filter');
        $serviceDate = $request->query('service_date');
        $calenderFilter = $request->query('calender_filter');
        $search = $request->input('hotel_name');
        $search_attraction = $request->input('attraction_name');

        $query = BookingItem::query()
            ->with(
                'booking:id,past_crm_id,payment_status',
                'product:id,name',
                'car:id,name',
                'variation:id,name',
                'room:id,name',
            )
            ->select(
                'id',
                'booking_id',
                'crm_id',
                'service_date',
                'product_type',
                'product_id',
                'payment_method',
                'payment_status',
                'reservation_status',
                'car_id'
            );

        if($request->date) {
            $start_date = Carbon::parse($request->date)->startOfMonth()->format('Y-m-d');
            $end_date = Carbon::parse($request->date)->endOfMonth()->format('Y-m-d');

            $query->whereBetween('service_date', [$start_date, $end_date]);
        }

        if ($serviceDate) {
            $query->whereDate('service_date', $serviceDate);
        };

        $productType = $request->query('product_type');
        $crmId = $request->query('crm_id');
        $oldCrmId = $request->query('old_crm_id');

        if ($crmId) {
            $query->whereHas('booking', function ($q) use ($crmId) {
                $q->where('crm_id', 'LIKE', "%{$crmId}%");
            });
        }

        if ($oldCrmId) {
            $query->whereHas('booking', function ($q) use ($oldCrmId) {
                $q->where('past_crm_id', 'LIKE', "%{$oldCrmId}%");
            });
        }

        if ($request->user_id) {
            $userId = $request->user_id;
            $query->whereHas('booking', function ($q) use ($userId) {
                $q->where('created_by', $userId)->orWhere('past_user_id', $userId);
            });
        }

        if ($productType) {
            $query->where('product_type', $productType);
        }

        if ($request->reservation_status) {
            $query->where('reservation_status', $request->reservation_status);
        }

        if ($request->booking_status) {
            $query->where('reservation_status', $request->booking_status);
        }

        if ($request->customer_payment_status) {
            $query->whereIn('booking_id', function ($q) use ($request) {
                $q->select('id')
                    ->from('bookings')
                    ->where('payment_status', $request->customer_payment_status);
            });
        }

        if ($request->expense_status) {
            $query->where('payment_status', $request->expense_status);
        }

        if ($calenderFilter == true) {
            $query->where('product_type', 'App\Models\PrivateVanTour')->orWhere('product_type', 'App\Models\GroupTour');
        }
        if($search) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            });
        }
        if($search_attraction) {
            $query->whereHas('variation', function ($q) use ($search_attraction) {
                $q->where('name', 'LIKE', "%{$search_attraction}%");
            });
        }
        if (Auth::user()->role === 'super_admin' || Auth::user()->role === 'reservation') {
            if ($filter) {
                if ($filter === 'past') {
                    $query->whereHas('booking', function ($q) {
                        $q->where('is_past_info', true)->whereNotNull('past_user_id');
                    });
                } elseif ($filter === 'current') {
                    $query->whereHas('booking', function ($q) {
                        $q->where('is_past_info', false)->whereNull('past_user_id');
                    });
                }
            }
        } else {
            $query->whereHas('booking', function ($q) {
                $q->where('created_by', Auth::id())->orWhere('past_user_id', Auth::id());
            });

            if ($filter) {
                if ($filter === 'past') {
                    $query->whereHas('booking', function ($q) {
                        $q->where('is_past_info', true)->where('past_user_id', Auth::id())->whereNotNull('past_user_id');
                    });
                } elseif ($filter === 'current') {
                    $query->whereHas('booking', function ($q) {
                        $q->where('created_by', Auth::id())->whereNull('past_user_id');
                    });
                }
            }
        }

        $query->orderBy('created_at', 'desc');

        $data = $query->paginate($limit);

        return $this->success(CalendarResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Reservation List For Calendar');
    }
}
