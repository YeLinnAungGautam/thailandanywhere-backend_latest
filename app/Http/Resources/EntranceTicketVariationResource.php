<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntranceTicketVariationResource extends JsonResource
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
            'price_name' => $this->price_name,
            'price' => $this->price,
            'cost_price' => $this->cost_price,
            'agent_price' => $this->agent_price,
            'description' => $this->description,
            'entrance_ticket' => new EntranceTicketResource($this->entranceTicket),
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
