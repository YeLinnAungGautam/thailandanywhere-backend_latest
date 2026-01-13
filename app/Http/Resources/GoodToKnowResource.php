<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodToKnowResource extends JsonResource
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
            'icon' => $this->icon,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'knowable_id' => $this->knowable_id,
            'knowable_type' => $this->knowable_type,
        ];
    }
}
