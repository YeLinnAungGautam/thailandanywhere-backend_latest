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
                // ✅ Use whereHas directly, not inside whereIn
                $booking_item_query = $booking_item_query
                    ->whereHas('reservationCarInfo', function ($q) use ($request) {
                        $q->where('supplier_id', $request->supplier_id);
                    });
            }
        }

        $paginated = $booking_item_query->orderBy('service_date', 'asc')->paginate($request->limit ?? 10);

        $supplierIds = $paginated->getCollection()
            ->pluck('reservationCarInfo.supplier_id')
            ->filter()->unique()->values();

        // Sum balance_amount which is already calculated in CarBookingResource
        $totalBalance = $paginated->getCollection()->sum(function ($item) {
            if ($item->is_driver_collect == 1) {
                return ($item->car_collect_amount ?? 0) - ($item->cost_price * $item->quantity ?? 0);
            }
            return -($item->cost_price * $item->quantity ?? 0);
        });

        return CarBookingResource::collection($paginated)
            ->additional([
                'result' => 1,
                'message' => 'success',
                'suppliers' => Supplier::whereIn('id', $supplierIds)->pluck('name', 'id')->toArray(),
                'supplierLists' => Supplier::pluck('name', 'id')->toArray(),
                'total_balance' => $request->supplier_id && is_numeric($request->supplier_id)
                    ? $totalBalance
                    : null,
            ]);
    }

    public function detail(string|int $booking_item_id)
    {
        $booking_item = BookingItem::privateVanTour()->with(
            'car',
            'booking',
            'product',
            'reservationCarInfo',
            'reservationInfo:id,booking_item_id,pickup_location,pickup_time',
            'booking.customer:id,name'
        )->find($booking_item_id);

        if (is_null($booking_item)) {
            return $this->error(null, "Car booking not found", 404);
        }

        return $this->success(new CarBookingResource($booking_item), 'Edit car booking');
    }

    /**
     * GET /admin/car-bookings/ledger
     *
     * Query params:
     *   - date_from  (required) e.g. 2024-06-01
     *   - date_to    (required) e.g. 2024-06-05
     *   - supplier_id (required, numeric)
     */
    public function getLedger(Request $request)
    {
        try {
            $request->validate([
                'date_from'   => 'required|date',
                'date_to'     => 'required|date|after_or_equal:date_from',
                'supplier_id' => 'required|integer|exists:suppliers,id',
            ]);

            $items = BookingItem::privateVanTour()
                ->with([
                    'product',
                    'booking.customer:id,name,id',
                    'booking:id,payment_method,payment_status,customer_id',
                    'reservationCarInfo.supplier:id,name',
                    'reservationCarInfo.driverInfo',
                    'reservationInfo:id,booking_item_id,pickup_location,pickup_time',
                ])
                ->whereHas('reservationCarInfo', function ($q) use ($request) {
                    $q->where('supplier_id', $request->supplier_id);
                })
                ->whereBetween('service_date', [$request->date_from, $request->date_to])
                ->orderBy('service_date', 'asc')
                ->get();

            // Group by date
            $grouped = $items->groupBy(fn ($item) => \Carbon\Carbon::parse($item->service_date)->format('Y-m-d'));

            $supplier = \App\Models\Supplier::find($request->supplier_id);

            $ledger = $grouped->map(function ($dayItems, $date) {
                $total_trips        = $dayItems->count();
                $total_sale_amount  = $dayItems->sum('amount');
                $total_cost_amount  = $dayItems->sum(fn ($i) => ($i->cost_price ?? 0) * ($i->quantity ?? 1));
                $total_collect      = $dayItems->sum(fn ($i) => $i->is_driver_collect == 1 ? ($i->car_total_collect ?? 0) : 0);
                $total_profit_loss  = $total_sale_amount - $total_cost_amount;

                // Balance = what driver collected minus cost (if driver collects), else negative cost
                $total_balance = $dayItems->sum(function ($i) {
                    if ($i->is_driver_collect == 1) {
                        return ($i->car_total_collect ?? 0) - (($i->cost_price ?? 0) * ($i->quantity ?? 1));
                    }
                    return -(($i->cost_price ?? 0) * ($i->quantity ?? 1));
                });

                $detail_items = $dayItems->map(function ($item) {
                    $cost = ($item->cost_price ?? 0) * ($item->quantity ?? 1);
                    $balance = $item->is_driver_collect == 1
                        ? ($item->car_total_collect ?? 0) - $cost
                        : -$cost;

                    return [
                        'id'               => $item->id,
                        'crm_id'           => $item->crm_id,
                        'customer_name'    => $item->booking?->customer?->name ?? null,
                        'booking_id'       => $item->booking?->id ?? null,
                        'product_name'     => $item->product?->name ?? null,
                        'pickup_time'      => $item->reservationInfo?->pickup_time ?? $item->pickup_time,
                        'pickup_location'  => $item->reservationInfo?->pickup_location ?? $item->pickup_location,
                        'qty'              => $item->quantity,
                        'sale_amount'      => $item->amount,
                        'cost_amount'      => $cost,
                        'profit_loss'      => ($item->amount ?? 0) - $cost,
                        'is_driver_collect'=> $item->is_driver_collect,
                        'car_total_collect'=> $item->car_total_collect,
                        'balance'          => $balance,
                        'is_checked'       => $item->is_checked,
                        'car_payment_method' => $item->car_payment_method,
                        'car_comment'      => $item->car_comment,
                    ];
                })->values();

                return [
                    'date'               => $date,
                    'total_trips'        => $total_trips,
                    'total_sale_amount'  => $total_sale_amount,
                    'total_cost_amount'  => $total_cost_amount,
                    'total_collect'      => $total_collect,
                    'total_profit_loss'  => $total_profit_loss,
                    'total_balance'      => $total_balance,
                    'items'              => $detail_items,
                ];
            })->values();

            // Grand totals across all days
            $grand = [
                'total_trips'       => $ledger->sum('total_trips'),
                'total_sale_amount' => $ledger->sum('total_sale_amount'),
                'total_cost_amount' => $ledger->sum('total_cost_amount'),
                'total_collect'     => $ledger->sum('total_collect'),
                'total_profit_loss' => $ledger->sum('total_profit_loss'),
                'total_balance'     => $ledger->sum('total_balance'),
            ];

            return $this->success([
                'supplier'    => [
                    'id'   => $supplier->id,
                    'name' => $supplier->name,
                ],
                'date_from'   => $request->date_from,
                'date_to'     => $request->date_to,
                'ledger'      => $ledger,
                'grand_total' => $grand,
            ], 'Ledger data');

        } catch (Exception $e) {
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
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

    // public function sendLine(string|int $booking_item_id, Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'message'     => 'required|string',
    //             'edited_data' => 'required|array',
    //         ]);

    //         $booking_item = BookingItem::privateVanTour()->find($booking_item_id);

    //         if (is_null($booking_item)) {
    //             return $this->error(null, 'Car booking not found', 404);
    //         }

    //         // ── 1. Save editable booking fields ──────────────────────────────
    //         $booking_item->update([
    //             'pickup_time'          => $request->pickup_time,
    //             'pickup_location'      => $request->pickup_location,
    //             'dropoff_location'     => $request->dropoff_location,
    //             'route_plan'           => $request->route_plan,
    //             'special_request'      => $request->special_request,
    //             'is_driver_collect'    => $request->is_driver_collect,
    //             'extra_collect_amount' => $request->is_driver_collect
    //                 ? $request->extra_collect_amount
    //                 : 0,
    //             // ── new fields ────────────────────────────────────────────────
    //             'car_customer_contact' => $request->car_customer_contact ?? null,
    //             'car_total_collect'    => $request->car_total_collect    ?? null,
    //             'car_payment_method'   => $request->car_payment_method   ?? null,
    //         ]);

    //         // ── 2. Append to line_history (diff computed inside model) ────────
    //         $booking_item->appendLineHistory($request->message, $request->edited_data);

    //         $history = $booking_item->fresh()->line_history;
    //         $latest  = end($history);
    //         $prev    = count($history) >= 2 ? $history[count($history) - 2] : null;

    //         // ── 3. Build the assign link ──────────────────────────────────────
    //         $frontendUrl = config('app.sale_url', 'http://localhost:5173');
    //         $assignLink  = "{$frontendUrl}/home/reservations?id={$booking_item->id}&crm_id={$booking_item->crm_id}";

    //         // ── 4. Build final LINE message ───────────────────────────────────
    //         $lineMessage = '';

    //         // Append --- Amendment --- block only if diff exists
    //         if (!empty($latest['diff'])) {
    //             $lineMessage .= "\n--- Amendment ---";
    //             foreach ($latest['diff'] as $field => $change) {
    //                 $label = ucwords(str_replace('_', ' ', $field));
    //                 $from  = ($change['from'] === null || $change['from'] === '') ? '(empty)' : $change['from'];
    //                 $to    = ($change['to']   === null || $change['to']   === '') ? '(empty)' : $change['to'];
    //                 $lineMessage .= "\n• {$label}: {$from} → {$to}";
    //             }
    //         }

    //         if ($prev) {
    //             $lineMessage .= "\n\n--- Update Detail --- \n";
    //         }

    //         $lineMessage .= $request->message;

    //         // Always append assign link at the bottom
    //         $lineMessage .= "\n\n🔗 Assign: {$assignLink}";

    //         return $this->success([
    //             'line_history' => $history,
    //             'sent_message' => $lineMessage,
    //         ], 'Booking saved successfully');

    //     } catch (Exception $e) {
    //         Log::error($e);
    //         return $this->error(null, $e->getMessage(), 500);
    //     }
    // }

    /**
     * Save booking + append line_history, then return the LINE message.
     * Handles three new fields: car_customer_contact, car_total_collect, car_payment_method.
     */
    public function sendLine(string|int $booking_item_id, Request $request)
    {
        try {
            $request->validate([
                'message'     => 'required|string',
                'edited_data' => 'required|array',
            ]);

            $booking_item = BookingItem::privateVanTour()->find($booking_item_id);

            if (is_null($booking_item)) {
                return $this->error(null, 'Car booking not found', 404);
            }

            // ── 1. Save editable booking fields ──────────────────────────────
            $booking_item->update([
                'pickup_time'          => $request->pickup_time,
                'pickup_location'      => $request->pickup_location,
                'dropoff_location'     => $request->dropoff_location,
                'route_plan'           => $request->route_plan,
                'special_request'      => $request->special_request,
                'is_driver_collect'    => $request->is_driver_collect,
                'extra_collect_amount' => $request->is_driver_collect
                    ? $request->extra_collect_amount
                    : 0,
                // ── new fields ────────────────────────────────────────────────
                'car_customer_contact' => $request->car_customer_contact ?? null,
                'car_total_collect'    => $request->car_total_collect    ?? null,
                'car_payment_method'   => $request->car_payment_method   ?? null,
            ]);

            // ── 2. Append to line_history (diff computed inside model) ────────
            $booking_item->appendLineHistory($request->message, $request->edited_data);

            $history = $booking_item->fresh()->line_history;
            $latest  = end($history);
            $prev    = count($history) >= 2 ? $history[count($history) - 2] : null;

            // ── 3. Build the assign link ──────────────────────────────────────
            $frontendUrl = config('app.sale_url', 'http://localhost:5173');
            $assignLink  = "{$frontendUrl}/vantour-management?id={$booking_item->id}&crm_id={$booking_item->crm_id}";

            // ── 4. Build final LINE message ───────────────────────────────────
            $lineMessage = '';

            // Append --- Amendment --- block only if diff exists
            if (!empty($latest['diff'])) {
                $lineMessage .= "\n--- Amendment ---";
                foreach ($latest['diff'] as $field => $change) {
                    $label = ucwords(str_replace('_', ' ', $field));
                    $from  = ($change['from'] === null || $change['from'] === '') ? '(empty)' : $change['from'];
                    $to    = ($change['to']   === null || $change['to']   === '') ? '(empty)' : $change['to'];
                    $lineMessage .= "\n• {$label}: {$from} → {$to}";
                }
            }

            if ($prev) {
                $lineMessage .= "\n\n--- Update Detail --- \n";
            }

            $lineMessage .= $request->message;

            // Always append assign link at the bottom
            $lineMessage .= "\n\n🔗 Assign: {$assignLink}";

            return $this->success([
                'line_history' => $history,
                'sent_message' => $lineMessage,
            ], 'Booking saved successfully');

        } catch (Exception $e) {
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
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

    public function getMonthlyGraph(Request $request)
    {
        try {
            // Default to current month if no month/year provided
            $year  = $request->year  ?? now()->year;
            $month = $request->month ?? now()->month;

            $query = BookingItem::privateVanTour()
                ->with([
                    'reservationCarInfo.supplier',
                    'booking.customer:id,name',
                    'reservationInfo:id,booking_item_id,pickup_location,pickup_time',
                    'car',
                    'product',
                ])
                ->whereYear('service_date', $year)
                ->whereMonth('service_date', $month)
                ->when($request->agent_id, function ($q) use ($request) {
                    $q->whereHas('booking', fn($q2) => $q2->where('created_by', $request->agent_id));
                });

            $items = $query->orderBy('service_date', 'asc')->get();

            // Group by date
            $grouped = $items->groupBy(fn($item) => $item->service_date->format('Y-m-d'));

            $graph_data = $grouped->map(function ($dayItems, $date) {
                $total      = $dayItems->count();
                $assigned   = $dayItems->filter(fn($i) => !is_null($i->reservationCarInfo?->supplier_id))->count();
                $cost_filled = $dayItems->filter(fn($i) => !is_null($i->cost_price) && !is_null($i->total_cost_price))->count();

                return [
                    'date'        => $date,
                    'total'       => $total,
                    'assigned'    => $assigned,
                    'cost_filled' => $cost_filled,
                    'unassigned'  => $total - $assigned,
                ];
            })->values();

            // Summary totals
            $summary = [
                'total'       => $items->count(),
                'assigned'    => $items->filter(fn($i) => !is_null($i->reservationCarInfo?->supplier_id))->count(),
                'cost_filled' => $items->filter(fn($i) => !is_null($i->cost_price) && !is_null($i->total_cost_price))->count(),
            ];

            return $this->success([
                'graph_data' => $graph_data,
                'summary'    => $summary,
                'year'       => (int) $year,
                'month'      => (int) $month,
            ], 'Monthly graph data');

        } catch (Exception $e) {
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function getDateDetail(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required|date',
            ]);

            $items = BookingItem::privateVanTour()
                ->with([
                    'car',
                    'product',
                    'booking.customer:id,name',
                    'reservationCarInfo.supplier',
                    'reservationCarInfo.driver',
                    'reservationCarInfo.driverInfo',
                    'reservationInfo:id,booking_item_id,pickup_location,pickup_time',
                ])
                ->whereDate('service_date', $request->date)
                ->when($request->agent_id, function ($q) use ($request) {
                    $q->whereHas('booking', fn($q2) => $q2->where('created_by', $request->agent_id));
                })
                ->orderBy('created_at', 'asc')
                ->get();

            $mapped = $items->map(function ($item) {
                return [
                    'id'              => $item->id,
                    'service_date'    => $item->service_date,
                    'customer_name'   => $item->booking?->customer?->name ?? null,
                    'product_name'    => $item->product?->name ?? null,
                    'pickup_time'     => $item->reservationInfo?->pickup_time ?? $item->pickup_time,
                    'pickup_location' => $item->reservationInfo?->pickup_location ?? $item->pickup_location,
                    'supplier_name'   => $item->reservationCarInfo?->supplier?->name ?? null,
                    'driver_name'     => $item->reservationCarInfo?->driver?->name ?? null,
                    'car_number'      => $item->reservationCarInfo?->driverInfo?->car_number ?? null,
                    'cost_price'      => $item->cost_price,
                    'total_cost_price'=> $item->total_cost_price,
                    // status flags
                    'is_assigned'     => !is_null($item->reservationCarInfo?->supplier_id),
                    'is_cost_filled'  => !is_null($item->cost_price) && !is_null($item->total_cost_price),
                ];
            });

            return $this->success([
                'date'  => $request->date,
                'items' => $mapped,
                'count' => $mapped->count(),
            ], 'Date detail');

        } catch (Exception $e) {
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
