<?php

namespace App\Http\Controllers;

use App\Http\Resources\HotelReportResource;
use App\Models\BookingItem;
use App\Models\Hotel;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HotelReportController extends Controller
{
    use HttpResponses;

    public function __invoke(Request $request)
    {
        $data = BookingItem::query()
            ->when($request->service_date, function ($q) use ($request) {
                $q->where('service_date', $request->service_date);
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
}
