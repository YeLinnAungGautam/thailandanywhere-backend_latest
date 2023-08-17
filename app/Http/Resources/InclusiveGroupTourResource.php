<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InclusiveGroupTourResource extends JsonResource
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
            'product' => new GroupTourResource($this->product),
            'car' => new CarResource($this->car),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
