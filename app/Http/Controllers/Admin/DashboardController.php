<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
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
}
