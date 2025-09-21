<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class HotelRoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $discount = hotel_discount();

        // Check if today falls within any period
        $today = Carbon::today();
        $currentPeriod = $this->periods()
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->first();

        // Use period prices if current period exists, otherwise use room default prices
        if ($currentPeriod) {
            $room_price = (float)$currentPeriod->sale_price;
            $cost_price = (float)$currentPeriod->cost_price;
            $owner_price = (float)$this->owner_price; // Keep original owner price for discount calculation
        } else {
            $room_price = (float)$this->room_price;
            $cost_price = (float)$this->cost;
            $owner_price = (float)$this->owner_price;
        }

        $discount_price = $room_price - $cost_price;
        $discount_price = $discount_price * $discount;

        if ($owner_price != 0) {
            $discount_percent = ($owner_price - ($room_price - $discount_price)) / $owner_price * 100;
        } else {
            $discount_percent = 0;
        }

        $selling_price = $room_price - $discount_price;

        $data = parent::toArray($request);
        $data['images'] = RoomImageResource::collection($this->images);
        $data['discount_price'] = $discount_price;
        $data['discount_percent'] = round($discount_percent);
        $data['selling_price'] = $selling_price;

        // Add period information if current period exists
        if ($currentPeriod) {
            $data['current_period'] = [
                'id' => $currentPeriod->id,
                'period_name' => $currentPeriod->period_name,
                'start_date' => $currentPeriod->start_date,
                'end_date' => $currentPeriod->end_date,
                'sale_price' => $currentPeriod->sale_price,
                'cost_price' => $currentPeriod->cost_price,
            ];
            $data['is_in_period'] = true;
        } else {
            $data['current_period'] = null;
            $data['is_in_period'] = false;
        }

        return $data;
    }
}
