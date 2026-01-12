<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class KeyHighLightResource extends JsonResource
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
            'title' => $this->title,
            'description_mm' => $this->description_mm,
            'description_en' => $this->description_en,
            'image_url' => $this->image_url ? Storage::url('images/' . $this->image_url) : null,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'highlightable_id' => $this->highlightable_id,
            'highlightable_type' => $this->highlightable_type,
        ];
    }
}
