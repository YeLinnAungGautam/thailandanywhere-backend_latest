<?php

namespace App\Http\Resources\Cart;

use App\Http\Resources\PrivateVanTourCarResource;
use App\Http\Resources\PrivateVanTourCityResource;
use App\Http\Resources\PrivateVanTourImageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PrivateVanTourCartResource extends JsonResource
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
            'description' => $this->description,
            'type' => $this->type,
            'long_description' => $this->long_description,
            'full_description' => $this->full_description,
            'full_description_en' => $this->full_description_en,
            'cover_image' => $this->cover_image ? Storage::url('images/' . $this->cover_image) : null,
            'cars' => PrivateVanTourCarResource::collection($this->cars),
            'cities' => PrivateVanTourCityResource::collection($this->cities),
            'images' => $this->images ? PrivateVanTourImageResource::collection($this->images) : null,
            'lowest_car_price' => $this->cars()->orderByPivot('price', 'asc')->first()->pivot->price ?? 0,
        ];
    }
}
