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
            'image' => $this->image ? Storage::url('images/' . $this->image) : null,
            'places' => $this->getPlaces(),
            'hotels' => $this->getHotels()
        ];
    }

    public function getPlaces()
    {
        return $this->hotels()
            ->selectRaw('place, COUNT(*) as hotel_count')
            ->groupBy('place')
            ->whereNotNull('place')
            ->get()
            ->map(fn($item) => [
                'name' => $item->place,
                'hotel_count' => $item->hotel_count,
            ]);
    }

    public function getHotels()
    {
        return $this->hotels()->count();
    }
}
