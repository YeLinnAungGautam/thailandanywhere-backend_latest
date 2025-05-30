<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PrivateVanTourResource extends JsonResource
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
            'sku_code' => $this->sku_code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'long_description' => $this->long_description,
            'full_description' => $this->full_description,
            'full_description_en' => $this->full_description_en,
            'cover_image' => $this->cover_image ? Storage::url('images/' . $this->cover_image) : null,
            'cars' => PrivateVanTourCarResource::collection($this->cars),
            'destinations' => PrivateVanTourDestinationResource::collection($this->destinations),
            'tags' => PrivateVanTourTagResource::collection($this->tags),
            'cities' => PrivateVanTourCityResource::collection($this->cities),
            'images' => $this->images ? PrivateVanTourImageResource::collection($this->images) : null,
            'lowest_car_price' => $this->cars()->orderByPivot('price', 'asc')->first()->pivot->price ?? 0,
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y H:i:s') : null,
            'total_booking_count' => $this->bookingItems()->count(),
            'with_ticket' => $this->with_ticket,
            'ticket_price' => $this->ticket_price,
        ];
    }
}
