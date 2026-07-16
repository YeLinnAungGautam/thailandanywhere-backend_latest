<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RoutePlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'route_id'            => $this->id,
            // vantour_ids array removed — use the relation instead
            'van_tours'           => VanTourV2Resource::collection($this->whenLoaded('vanTours')),
            'destination_ids'     => $this->destination_ids ?? [],
            'city_ids'            => $this->city_ids ?? [],
            'main_cover_photo'    => $this->main_cover_photo
                ? Storage::url('images/' . $this->main_cover_photo)
                : null,
            'other_photos'        => collect($this->other_photos ?? [])
                ->map(fn ($photo) => Storage::url('images/' . $photo))
                ->values(),
            'english_description' => $this->english_description,
            'mm_description'      => $this->mm_description,
            'route'               => $this->route,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
