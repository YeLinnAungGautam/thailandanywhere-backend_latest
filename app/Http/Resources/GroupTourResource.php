<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class GroupTourResource extends JsonResource
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
            'price' => $this->price,
            'description' => $this->description,
            'full_description' => $this->full_description,
            'full_description_en' => $this->full_description_en,
            'cover_image' => $this->cover_image ? config('app.url') . Storage::url('images/' . $this->cover_image) : null,
            'destinations' => PrivateVanTourDestinationResource::collection($this->destinations),
            'tags' => PrivateVanTourTagResource::collection($this->tags),
            'cities' => PrivateVanTourCityResource::collection($this->cities),
            'images' => $this->images ? PrivateVanTourImageResource::collection($this->images) : null,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
