<?php

namespace App\Http\Resources;

use App\Models\BookingItem;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sale_counts = BookingItem::query()->where('product_type', Hotel::class)->count();
        $percentage = ($this->total_bookings / $sale_counts) * 100;

        return [
            'hotel_id' => $this->product_id,
            'hotel_name' => $this->product->name ?? '-',
            'total_bookings' => $this->total_bookings,
            'percentage' => number_format($percentage, 2)
        ];
    }
}
