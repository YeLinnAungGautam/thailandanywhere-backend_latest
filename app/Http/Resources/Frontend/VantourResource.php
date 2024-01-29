<?php

namespace App\Http\Resources\Frontend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class VantourResource extends JsonResource
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
            'cover_image' => $this->cover_image ? config('app.url') . Storage::url('images/' . $this->cover_image) : null,
        ];
    }
}
