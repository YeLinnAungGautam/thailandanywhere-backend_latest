<?php

namespace App\Http\Controllers;

use App\Http\Resources\HotelReportResource;
use App\Models\BookingItem;
use App\Models\Hotel;
use App\Traits\HttpResponses;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HotelReportController extends Controller
{
    use HttpResponses;

    public function __invoke(Request $request)
    {
        $data = BookingItem::query()
            ->when($request->service_date, function ($q) use ($request) {
                if($this->isValidDate($request->service_date)) {
                    $q->where('service_date', $request->service_date);
                } else {
                    $dates = explode('-', $request->service_date);

                    $q->whereMonth('service_date', $dates[1])->whereYear('service_date', $dates[0]);
                }
            })
            ->with('product:id,name')
            ->where('product_type', Hotel::class)
            ->select('product_id', 'product_type', DB::raw('count(*) as total_bookings'))
            ->groupBy('product_id', 'product_type')
            ->orderBy('total_bookings', 'desc')
            ->limit($request->limit ?? 10)
            ->get();

        return $this->success(HotelReportResource::collection($data));
    }

    private function isValidDate($date, $format = 'Y-m-d')
    {
        $dateTime = DateTime::createFromFormat($format, $date);

        return $dateTime && $dateTime->format($format) === $date;
    }
}
