<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DestinationResource extends JsonResource
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
            'category' => new ProductCategoryResource($this->category),
            'description' => $this->description,
            'entry_fee' => $this->entry_fee,

            'city' => new CityResource($this->city),
            'feature_img' => $this->image ? config('app.url') . Storage::url('images/destination/' . $this->image) : null,
            'summary' => $this->summary,
            'detail' => $this->detail,
            'place_id' => $this->place_id,
            'images' => ProductImageResource::collection($this->images),

            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
