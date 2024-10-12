<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class InclusiveResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku_code' => $this->sku_code,
            'price' => $this->price,
            'agent_price' => $this->agent_price,
            'day' => $this->day,
            'night' => $this->night,
            'description' => $this->description,
            'cover_image' => $this->cover_image ? Storage::url('images/' . $this->cover_image) : null,
            'images' => $this->images ? PrivateVanTourImageResource::collection($this->images) : null,
            'private_van_tours' => InclusivePrivateVanTourResource::collection($this->privateVanTours),
            'group_tours' => InclusiveGroupTourResource::collection($this->groupTours),
            'entrance_tickets' => InclusiveEntranceTicketResource::collection($this->entranceTickets),
            'airport_pickups' => InclusiveAirportPickupResource::collection($this->airportPickups),
            'airline_tickets' => InclusiveAirlineTicketResource::collection($this->airlineTickets),
            'hotels' => InclusiveHotelResource::collection($this->hotels),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
