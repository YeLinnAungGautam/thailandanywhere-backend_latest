<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PrivateVanTourListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $discount = vantour_discount();
        $lowest_cost_price = $this->cars()->orderByPivot('cost', 'asc')->first()->pivot->cost ?? 0;
        $lowest_car_price = $this->cars()->orderByPivot('price', 'asc')->first()->pivot->price ?? 0;
        $discount_price = $lowest_cost_price == 0 ? 0 : ((float) $lowest_car_price - (float) $lowest_cost_price) * $discount;
        $selling_price = (float) $lowest_car_price - (float) $discount_price;
        return [
            'id' => $this->id,
            'name' => $this->name,
            'discount' => $discount,
            'selling_price' => $selling_price,
            'description' => $this->description,
            'type' => $this->type,
            'long_description' => $this->long_description,
            'full_description' => $this->full_description,
            'full_description_en' => $this->full_description_en,
            'cover_image' => $this->cover_image ? Storage::url('images/' . $this->cover_image) : null,
            'destinations' => PrivateVanTourDestinationResource::collection($this->destinations),
            'cities' => PrivateVanTourCityResource::collection($this->cities),
            'images' => $this->images ? PrivateVanTourImageResource::collection($this->images) : null,
            'lowest_car_price' => $lowest_car_price,
            'lowest_cost_price' => $lowest_cost_price,
            'total_booking_count' => $this->bookingItems()->count(),
            'ticket_price' => $this->ticket_price,
        ];
    }
}
