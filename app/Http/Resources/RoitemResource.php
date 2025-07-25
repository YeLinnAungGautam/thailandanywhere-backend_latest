<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RoitemResource extends JsonResource
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
            'icon' => $this->icon ? Storage::url('icons/' . $this->icon) : null,
            'rofacility_id' => $this->rofacility_id,
            'rofacility' => new RofacilityResource($this->whenLoaded('rofacility')), // Assuming Roitem has a relationship with Rofacility
        ];
    }
}
