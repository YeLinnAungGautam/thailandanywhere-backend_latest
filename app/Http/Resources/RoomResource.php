<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hotel' => new HotelResource($this->hotel),
            'extra_price' => $this->extra_price,
            'room_price' => $this->getRoomPrice($request->period),
            'is_extra' => $this->is_extra,
            'cost' => $this->cost,
            'description' => $this->description,
            'images' => RoomImageResource::collection($this->images),
            'room_periods' => RoomPeriodResource::collection($this->periods),
            'max_person' => $this->max_person,
            'deleted_at' => $this->deleted_at,
            'updated_at' => $this->updated_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function getRoomPrice($period = null)
    {
        if(is_null($period)) {
            return $this->room_price;
        }

        $periods = $period ? explode(' , ', $period) : null;
        $dates = $this->getDaysOfMonth($periods[0], $periods[1]);

        $room_prices = [];
        foreach($dates as $date) {
            $query = $this->periods()
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date);

            if($query->exists()) {
                $room_prices[] = $query->sum('sale_price');
            } else {
                $room_prices[] = $this->room_price;
            }
        }

        return array_sum($room_prices);
    }

    private function getDaysOfMonth($start_date, $end_date): array
    {
        $dates = Carbon::parse($start_date)
            ->daysUntil(Carbon::parse($end_date))
            ->map(fn ($date) => $date->format('Y-m-d'));

        return iterator_to_array($dates);
    }
}
