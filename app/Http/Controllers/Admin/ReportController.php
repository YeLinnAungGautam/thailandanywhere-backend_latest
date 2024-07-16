<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Customer;
use App\Services\SaleReportService;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $from = $request->start_date;
        $to = $request->end_date;

        if($from != '' && $to != '') {

            $sales_amount = $this->salesAmount($from, $to);
            $sales_count = $this->salesCount($from, $to);
            $bookings = $this->bookingsCount($from, $to);
            $reservations = $this->reservationsCount($from, $to);

            $date = 'From: '.Carbon::parse($from)->format('d F Y').' To: '.Carbon::parse($to)->format('d F Y');

        } else {

            $sales_amount = $this->salesAmount();
            $sales_count = $this->salesCount();
            $bookings = $this->bookingsCount();
            $reservations = $this->reservationsCount();

            $date = Carbon::now()->format('d F Y');

        }


        $data = [
            'sales' => $sales_amount,
            'sales_count' => $sales_count,
            'bookings' => $bookings,
            'reservations' => $reservations
        ];

        return $this->success($data, $date);

    }

    public function getSelectData($date)
    {
        $sales_amount = $this->salesData($date);
        $sales_count = $this->salesCountData($date);
        $bookings = $this->bookingsData($date);
        $reservations = $this->reservationsData($date);

        $date = 'Date: '.Carbon::parse($date)->format('d F Y');

        $data = [
            'sales' => $sales_amount,
            'sales_count' => $sales_count,
            'bookings' => $bookings,
            'reservations' => $reservations
        ];

        return $this->success($data, $date);

    }

    /**
     * Get all bookings sales.
     */
    public function salesAmount($from = '', $to = '')
    {

        if($from != '' && $to != '') {
            $data = Booking::query()
                ->select('created_by', DB::raw('SUM(grand_total) as total'))
                ->groupBy('created_by')
                ->whereBetween('created_at', [date($from), date($to)])
                ->get();

        } else {
            $data = Booking::query()
                ->select('created_by', DB::raw('SUM(grand_total) as total'))
                ->groupBy('created_by')
                ->get();
        }

        foreach($data as $result) {
            $agents[] = $result->createdBy->name;
            $amount[] = $result->total;
        }

        $data = [
            'agents' => isset($agents) ? $agents : null,
            'amount' => isset($amount) ? $amount : null,
        ];

        return $this->success($data, 'Sales Amount');

    }

    public function salesCount($from = '', $to = '')
    {
        if($from != '' && $to != '') {
            $data = Booking::query()
                ->select('created_by', DB::raw('COUNT(grand_total) as total'))
                ->groupBy('created_by')
                ->whereBetween('created_at', [date($from), date($to)])
                ->get();
        } else {
            $data = Booking::query()
                ->select('created_by', DB::raw('COUNT(grand_total) as total'))
                ->groupBy('created_by')
                ->get();
        }

        foreach($data as $result) {
            $agents[] = $result->createdBy->name;
            $amount[] = $result->total;
        }

        $data = [
            'agents' => isset($agents) ? $agents : null,
            'amount' => isset($amount) ? $amount : null,
        ];

        return $this->success($data, 'Sales Count');

    }

    public function bookingsCount($from = '', $to = '')
    {
        if($from != '' && $to != '') {
            $data = Booking::query()
                ->select('created_by', DB::raw('COUNT(id) as total'))
                ->groupBy('created_by')
                ->whereBetween('created_at', [date($from), date($to)])
                ->get();
        } else {

            $data = Booking::query()
                ->select('created_by', DB::raw('COUNT(id) as total'))
                ->groupBy('created_by')
                ->get();
        }

        foreach($data as $result) {
            $agents[] = $result->createdBy->name;
            $booking[] = $result->total;
        }

        $data = [
            'agents' => isset($agents) ? $agents : null,
            'bookings' => isset($booking) ? $booking : null,
        ];

        return $this->success($data, 'Bookings Count');

    }

    public function reservationsCount($from = '', $to = '')
    {

        $query = Booking::query();

        if($from != '' && $to != '') {
            $query->whereBetween('created_at', [date($from), date($to)]);

        }
        $data = $query->get();

        $results = BookingResource::collection($data);

        $items = [];
        $one = [];
        foreach($results as $key => $res) {
            foreach($res->items as $res1) {
                $reserve_types = substr($res1->product_type, 11);

                if($reserve_types == 'Hotel') {

                    $datetime1 = new DateTime($res1->checkin_date);
                    $datetime2 = new DateTime($res1->checkout_date);
                    $interval = $datetime1->diff($datetime2);
                    $days = $interval->format('%a');

                    $price = $res1->quantity * $res1->selling_price * $days;

                } else {

                    $price = $res1->quantity * $res1->selling_price;

                }

                $one[] = [
                    'product_type' => $reserve_types,
                    'price' => $price
                ];

                $items[] = [
                    'product_type' => $reserve_types,
                    'prices' => $price
                ];
            }
        }

        $count_bookings = array_count_values(array_column($items, 'product_type'));

        foreach($count_bookings as $value) {
            $booking[] = $value;
        }

        $new_array = [];
        foreach ($one as $value) {
            if(array_key_exists($value['product_type'], $new_array)) {
                $value['price'] += $new_array[$value['product_type']]['price'];
            }
            $new_array[$value['product_type']] = $value;
        }

        foreach($new_array as $res) {
            $type[] = $res['product_type'];
            $prices[] = $res['price'];

        }
        $data = [
            'agents' => isset($type) ? $type : null,
            'amount' => isset($booking) ? $booking : null,
            'prices' => isset($prices) ? $prices : null,
        ];

        return $this->success($data, 'Reservations Count');
    }

    public function salesData($date)
    {
        $data = Booking::query()
            ->select('created_by', DB::raw('SUM(grand_total) as total'))
            ->groupBy('created_by')
            ->whereDate('created_at', Carbon::parse($date)->format('Y-m-d'))
            ->get();

        foreach($data as $result) {
            $agents[] = $result->createdBy->name;
            $amount[] = $result->total;
        }

        $data = [
            'agents' => isset($agents) ? $agents : null,
            'amount' => isset($amount) ? $amount : null,
        ];

        return $this->success($data, 'Sales Amount');

    }

    public function salesCountData($date)
    {

        $data = Booking::query()
            ->select('created_by', DB::raw('COUNT(grand_total) as total'))
            ->groupBy('created_by')
            ->whereDate('created_at', '=', date($date))
            ->get();


        foreach($data as $result) {
            $agents[] = $result->createdBy->name;
            $amount[] = $result->total;
        }

        $data = [
            'agents' => isset($agents) ? $agents : null,
            'amount' => isset($amount) ? $amount : null,
        ];

        return $this->success($data, 'Sales Count');

    }

    public function bookingsData($date)
    {

        $data = Booking::query()
            ->select('created_by', DB::raw('COUNT(id) as total'))
            ->groupBy('created_by')
            ->whereDate('created_at', '=', date($date))
            ->get();

        foreach($data as $result) {
            $agents[] = $result->createdBy->name;
            $booking[] = $result->total;
        }

        $data = [
            'agents' => isset($agents) ? $agents : null,
            'bookings' => isset($booking) ? $booking : null,
        ];

        return $this->success($data, 'Bookings Count');

    }

    public function reservationsData($date)
    {

        $query = Booking::query();

        $query->whereDate('created_at', date($date));

        $data = $query->get();

        $results = BookingResource::collection($data);

        $items = [];
        $one = [];

        foreach($results as $key => $res) {
            foreach($res->items as $res1) {
                $reserve_types = substr($res1->product_type, 11);

                if($reserve_types == 'Hotel') {

                    $datetime1 = new DateTime($res1->checkin_date);
                    $datetime2 = new DateTime($res1->checkout_date);
                    $interval = $datetime1->diff($datetime2);
                    $days = $interval->format('%a');

                    $price = $res1->quantity * $res1->selling_price * $days;
                } else {
                    $price = $res1->quantity * $res1->selling_price;
                }

                $one[] = [
                    'product_type' => $reserve_types,
                    'price' => $price
                ];

                $items[] = [
                    'product_type' => $reserve_types,
                    'prices' => $price
                ];
            }
        }

        $count_bookings = array_count_values(array_column($items, 'product_type'));

        foreach($count_bookings as $value) {
            $booking[] = $value;
        }

        $new_array = [];
        foreach ($one as $value) {
            if(array_key_exists($value['product_type'], $new_array)) {
                $value['price'] += $new_array[$value['product_type']]['price'];
            }
            $new_array[$value['product_type']] = $value;
        }

        foreach($new_array as $res) {
            $type[] = $res['product_type'];
            $prices[] = $res['price'];

        }
        $data = [
            'agents' => isset($type) ? $type : null,
            'amount' => isset($booking) ? $booking : null,
            'prices' => isset($prices) ? $prices : null,
        ];

        return $this->success($data, 'Reservations Count');
    }

    //     public function getEachUserSaleCount()
    // {
    //     $data = Booking::select('created_by', DB::raw('COUNT(grand_total) as total'))
    //         ->groupBy('created_by')
    //         ->get();

    //     foreach ($data as $result) {
    //         $agents[] = $result->createdBy->name;
    //         $amount[] = $result->total;
    //     }

    //     $responseData = array(
    //         'agents' => isset($agents) ? $agents : null,
    //         'amount' => isset($amount) ? $amount : null,
    //     );

    //     return $this->success($responseData, 'Sales Count');
    // }
    public function getEachUserSaleCount()
    {
        $data = Booking::query()
            ->select('created_by', DB::raw('COUNT(grand_total) as total'))
            ->groupBy('created_by')
            ->get();

        $responseData = [
            'agents' => $data->pluck('createdBy.name')->toArray(),
            'amount' => $data->pluck('total')->toArray(),
        ];

        return $this->success($responseData, 'Sales Count');
    }
    // public function getEachUserSaleCount($selectedMonth)
    // {
    //     $currentMonth = Carbon::now()->format('m');

    //     if ($selectedMonth != $currentMonth) {
    //         // The selected month is not the current month, handle this case as needed
    //         return $this->error('Invalid month selected', 400);
    //     }

    //     $data = Booking::select('created_by', DB::raw('COUNT(grand_total) as total'))
    //         ->groupBy('created_by')
    //         ->whereMonth('created_at', '=', $selectedMonth)
    //         ->get();

    //     $responseData = [
    //         'agents' => $data->pluck('createdBy.name')->toArray(),
    //         'amount' => $data->pluck('total')->toArray(),
    //     ];

    //     return $this->success($responseData, 'Sales Count');
    // }

    public function getCustomerSale()
    {
        $customers = Customer::with(['items' => function ($q) {
            $q->select(
                DB::raw('SUBSTR(product_type,12)as product')
            );
        }])->get();

        foreach($customers as $s) {
            $data[] = [
                'name' => $s->name,
                'items' => $s->items
            ];
        }

        return $this->success($data, 'Customers Sale');

    }

    public function generalSaleReport(string $date, Request $request)
    {
        $report_service = new SaleReportService($date);

        $data = [
            'sales' => $report_service->getSaleData($request->created_by),
            'sales_count' => $report_service->getSaleCountData($request->created_by),
            'bookings' => $report_service->getBookingData($request->created_by),
        ];

        return $this->success($data, 'Date: ' . Carbon::parse($date)->format('d F Y'));
    }
}
