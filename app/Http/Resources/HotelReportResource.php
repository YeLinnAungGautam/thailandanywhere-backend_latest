<?php

namespace App\Http\Resources;

use App\Models\BookingItem;
use App\Models\Hotel;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sale_counts = BookingItem::query()
            ->where('product_type', Hotel::class)
            ->when($request->service_date, function ($q) use ($request) {
                if($this->isValidDate($request->service_date)) {
                    $q->where('service_date', $request->service_date);
                } else {
                    $dates = explode('-', $request->service_date);

                    $q->whereMonth('service_date', $dates[1])->whereYear('service_date', $dates[0]);
                }
            })
            ->count();

        $percentage = ($this->total_bookings / $sale_counts) * 100;

        return [
            'hotel_id' => $this->product_id,
            'hotel_name' => $this->product->name ?? '-',
            'total_bookings' => $this->total_bookings,
            'percentage' => number_format($percentage, 2)
        ];
    }

    private function isValidDate($date, $format = 'Y-m-d')
    {
        $dateTime = DateTime::createFromFormat($format, $date);

        return $dateTime && $dateTime->format($format) === $date;
    }
}
