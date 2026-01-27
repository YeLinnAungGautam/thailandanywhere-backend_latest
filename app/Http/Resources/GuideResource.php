<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class GuideResource extends JsonResource
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
            'licence' => $this->licence ? Storage::url('images/' . $this->licence) : null,
            'contact' => $this->contact,
            'image' => $this->image ? Storage::url('images/' . $this->image) : null,
            'notes' => $this->notes,
            'renew_score' => $this->renew_score,
            'day_rate' => $this->day_rate,
            'is_active' => $this->is_active,
            'languages' => $this->languages,
            'cities' => CityResource::collection($this->whenLoaded('cities')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
