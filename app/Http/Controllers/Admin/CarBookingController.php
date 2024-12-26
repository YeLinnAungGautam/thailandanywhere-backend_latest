<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarBookingRequest;
use App\Http\Resources\CarBookingResource;
use App\Models\BookingItem;
use App\Models\Supplier;
use App\Services\BookingItemDataService;
use App\Services\Repository\CarBookingRepositoryService;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CarBookingController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $booking_item_query = BookingItem::privateVanTour()
            ->with(
                'car',
                'booking',
                'product',
                'reservationCarInfo',
                'reservationInfo:id,booking_item_id,pickup_location,pickup_time',
                'booking.customer:id,name'
            )
            ->when($request->daterange, function ($query) use ($request) {
                $dates = explode(',', $request->daterange);

                $query->where('service_date', '>=', $dates[0])->where('service_date', '<=', $dates[1]);
            })
            ->when($request->agent_id, function ($query) use ($request) {
                $query->whereHas('booking', fn ($q) => $q->where('created_by', $request->agent_id));
            })
            ->when($request->assigned_only, function ($query) {
                $query->whereHas('reservationCarInfo');
            });

        if ($request->supplier_id) {
            if ($request->supplier_id === 'unassigned') {
                $booking_item_query = $booking_item_query->whereDoesntHave('reservationCarInfo')
                    ->orWhereIn('id', function ($query) {
                        $query->select('booking_item_id')->from('reservation_car_infos')->whereNull('supplier_id');
                    })
                    ->when($request->daterange, function ($query) use ($request) {
                        $dates = explode(',', $request->daterange);

                        $query->where('service_date', '>=', $dates[0])->where('service_date', '<=', $dates[1]);
                    });
            } else {
                $booking_item_query = $booking_item_query
                    ->whereIn('id', function ($query) use ($request) {
                        // $query->select('booking_item_id')->from('reservation_car_infos')->where('supplier_id', $request->supplier_id);
                        $query->whereHas('reservationCarInfo', function ($q) use ($request) {
                            $q->where('supplier_id', $request->supplier_id);
                        });
                    });
            }
        }

        $booking_item_query->orderByDESC('created_at');

        return CarBookingResource::collection($booking_item_query->paginate($request->limit ?? 10))
            ->additional([
                'result' => 1,
                'message' => 'success',
                'suppliers' => Supplier::pluck('name', 'id')->toArray(),
            ]);
    }

    public function edit(string|int $booking_item_id)
    {
        $booking_item = BookingItem::privateVanTour()->find($booking_item_id);

        if (is_null($booking_item)) {
            return $this->error(null, "Car booking not found", 404);
        }

        return $this->success(CarBookingRepositoryService::getCarBooking($booking_item), 'Edit car booking');
    }

    public function update(string|int $booking_item_id, CarBookingRequest $request)
    {
        try {
            $booking_item = BookingItem::privateVanTour()->find($booking_item_id);

            if (is_null($booking_item)) {
                throw new Exception('Car booking not found');
            }

            $data = CarBookingRepositoryService::updateBooking($booking_item, $request);

            return $this->success($data, 'Car booking updated successfully');
        } catch (Exception $e) {
            $this->error(null, $e->getMessage(), 500);
        }
    }

    public function getSummary(Request $request)
    {
        return $this->success(BookingItemDataService::getCarBookingSummary($request->all()), 'Success car booking summary');
    }

    public function completePercentage(Request $request)
    {
        try {
            $auth_user = auth()->user();

            $query = BookingItem::privateVanTour()
                ->with(
                    'car',
                    'booking',
                    'product',
                    'reservationCarInfo',
                    'reservationInfo:id,booking_item_id,pickup_location,pickup_time',
                    'booking.customer:id,name'
                )
                ->when($request->daterange, function ($query) use ($request) {
                    $dates = explode(',', $request->daterange);

                    $query->where('service_date', '>=', $dates[0])->where('service_date', '<=', $dates[1]);
                });

            if ($auth_user->role != 'super_admin') {
                $query->whereHas('booking', fn ($q) => $q->where('created_by', $auth_user->id));
            }

            $total = $query->count();
            $admin_needed = 0;
            $sale_needed = 0;
            $reservation_needed = 0;

            foreach ($query->cursor() as $booking_item) {
                $admin = [];
                $sale = [];
                $reservation = [];

                if (
                    is_null($booking_item->reservationCarInfo) ||
                    is_null($booking_item->reservationCarInfo->supplier) ||
                    is_null($booking_item->reservationCarInfo->driverInfo) ||
                    is_null($booking_item->reservationCarInfo->driverInfo->driver) ||
                    is_null($booking_item->reservationCarInfo->driverInfo->driver->contact) ||
                    is_null($booking_item->cost_price) ||
                    is_null($booking_item->total_cost_price)
                ) {
                    $admin[] = 1;
                    $reservation[] = 1;
                }

                if (
                    is_null($booking_item->pickup_time) ||
                    is_null($booking_item->route_plan) ||
                    is_null($booking_item->special_request)
                ) {
                    $admin[] = 1;
                    $sale[] = 1;
                }

                if ($booking_item->is_driver_collect && is_null($booking_item->extra_collect_amount)) {
                    $admin[] = 1;
                    $sale[] = 1;
                }

                if (!empty($admin)) {
                    $admin_needed += 1;
                }

                if (!empty($sale)) {
                    $sale_needed += 1;
                }

                if (!empty($reservation)) {
                    $reservation_needed += 1;
                }
            }

            $needed = 0;
            switch ($auth_user->role) {
                case 'admin':
                    $needed = $sale_needed;

                    break;

                case 'reservation':
                    $needed = $reservation_needed;

                    break;

                default:
                    $needed = $admin_needed;

                    break;
            }

            return success([
                'total' => $total,
                'needed' => $needed,
                'needed_percentage' => $total > 0 ? number_format($needed / $total * 100, 2) : 100,
                'complete_percentage' => $total > 0 ? number_format(($total - $needed) / $total * 100, 2) : 100,
            ]);
        } catch (Exception $e) {
            Log::error($e);

            return failedMessage('Something went wrong! Please contact to admin.');
        }
    }
}
