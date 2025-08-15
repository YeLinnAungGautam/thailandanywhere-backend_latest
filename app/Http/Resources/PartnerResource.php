<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerResource extends JsonResource
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
            'email' => $this->email,
            'role' => $this->role,
            'parent_id' => $this->parent_id,
            'hotels' => $this->whenLoaded('hotels'),
            'entranceTickets' => $this->whenLoaded('entranceTickets'),
            'created_at' => $this->created_at,
            'login_count' => $this->login_count
        ];
    }
}
