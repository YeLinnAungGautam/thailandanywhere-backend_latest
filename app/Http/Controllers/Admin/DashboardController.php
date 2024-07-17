<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TopSellingProductResource;
use App\Http\Resources\UnpaidBookingResource;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\ReportService;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use HttpResponses;

    public function reportByChannel(Request $request)
    {
        try {
            if(!$request->daterange) {
                throw new Exception('Report by channel: Invalid daterange to filter');
            }

            $dates = explode(',', $request->daterange);

            $results = Booking::query()
                ->whereBetween('booking_date', [$dates[0], $dates[1]])
                ->groupBy('sold_from')
                ->select(
                    'sold_from',
                    DB::raw('SUM(grand_total) as total_amount'),
                )
                ->get();

            return $this->success($results, 'Channel from sold');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function reportByPaymentMethod(Request $request)
    {
        try {
            if(!$request->daterange) {
                throw new Exception('Report by payment method: Invalid daterange to filter');
            }

            $dates = explode(',', $request->daterange);

            $results = Booking::query()
                ->whereBetween('booking_date', [$dates[0], $dates[1]])
                ->groupBy('payment_method')
                ->select(
                    'payment_method',
                    DB::raw('SUM(grand_total) as total_amount'),
                )
                ->get();

            return $this->success($results, 'Method of payments');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function reportByPaymentStatus(Request $request)
    {
        try {
            if(!$request->daterange) {
                throw new Exception('Report by payment status: Invalid daterange to filter');
            }

            $dates = explode(',', $request->daterange);

            $results = Booking::query()
                ->whereBetween('booking_date', [$dates[0], $dates[1]])
                ->groupBy('payment_status')
                ->select(
                    'payment_status',
                    DB::raw('SUM(grand_total) as total_amount'),
                )
                ->get();

            return $this->success($results, 'Method of payments');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function reportByPaymentAndProduct(Request $request)
    {
        try {
            if(!$request->daterange) {
                throw new Exception('Report by payment status: Invalid daterange to filter');
            }

            $dates = explode(',', $request->daterange);

            $results = Booking::query()
                ->join('booking_items', 'bookings.id', 'booking_items.booking_id')
                ->whereBetween('bookings.booking_date', [$dates[0], $dates[1]])
                ->select(
                    'bookings.payment_currency',
                    'booking_items.product_type',
                    // DB::raw('SUM(bookings.grand_total) as total_amount'),
                    DB::raw('SUM(booking_items.selling_price) as total_selling_amount'),
                )
                ->groupBy(
                    'bookings.payment_currency',
                    'booking_items.product_type'
                )
                ->get();

            return $this->success($results, 'Report by payment currency and product type');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function getUnpaidBooking(Request $request)
    {
        try {
            if(!$request->daterange) {
                throw new Exception('Unpaid Booking: Invalid daterange to filter');
            }

            $data = ReportService::getUnpaidBooking($request->daterange, $request->agent_id, $request->service_daterange);

            return $this->success(UnpaidBookingResource::collection($data), 'Sale count report');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function salesByAgentReport(Request $request)
    {
        try {
            if(!$request->daterange) {
                throw new Exception('Sale By Agent: Invalid daterange to filter');
            }

            return $this->success(ReportService::getSalesByAgent($request->daterange), 'Report sales by agents');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function getSaleCounts(Request $request)
    {
        try {
            if(!$request->daterange) {
                throw new Exception('Sale Cunt: Invalid daterange to filter');
            }

            return $this->success(ReportService::getCountReport($request->daterange), 'Sale count report');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function getTopSellingProduct(Request $request)
    {
        try {
            if(!$request->daterange) {
                throw new Exception('Report by payment status: Invalid daterange to filter');
            }

            $data = ReportService::getTopSellingProduct($request->daterange, $request->product_type, $request->limit);

            return $this->success(TopSellingProductResource::collection($data), 'Sale count report');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function saleReportByDate(Request $request)
    {
        try {
            if(!$request->created_at) {
                throw new Exception('Report by date: Invalid date to filter');
            }

            $bookings = Booking::whereDate('created_at', $request->created_at)->get();

            $booking_items = BookingItem::query()
                ->select('id', 'product_id', 'product_type', 'amount')
                ->whereIn('booking_id', $bookings->pluck('id')->toArray())
                ->get();

            $data = [
                'sub_total_amount' => $bookings->sum('sub_total'),
                'grand_total_amount' => $bookings->sum('grand_total'),
                'total_discount' => $bookings->sum('discount'),
                'reservations' => $booking_items->sum('amount')
            ];

            return success($data);

        } catch (Exception $e) {
            return failedMessage($e->getMessage());
        }
    }
}
