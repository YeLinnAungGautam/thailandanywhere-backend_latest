<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HotelCityResource extends JsonResource
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
            'image' => $this->image ? config('app.url') . Storage::url('images/' . $this->image) : null,
            'places' => $this->getPlaces()
        ];
    }

    public function getPlaces()
    {
        return $this->hotels()->pluck('place')->unique();
    }
}
