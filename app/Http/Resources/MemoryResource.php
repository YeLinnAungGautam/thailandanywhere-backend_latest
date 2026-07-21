<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'caption'        => $this->caption,
            'status'         => $this->status,
            'location'       => $this->location ?? null,
            'likes_count'    => $this->likes_count ?? 0,
            'comments_count' => $this->comments_count ?? 0,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,

            'user' => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),

            'images' => MemoryImageResource::collection($this->whenLoaded('images')),

            'booking' => $this->whenLoaded('booking', fn () => $this->booking ? [
                'id'   => $this->booking->id,
                // Booking has no generic "name" column — crm_id is its display name.
                'name' => $this->booking->crm_id,
            ] : null),

            'booking_item' => $this->whenLoaded('bookingItem', fn () => $this->bookingItem ? [
                'id'      => $this->bookingItem->id,
                'product' => $this->bookingItem->relationLoaded('product') && $this->bookingItem->product
                    ? [
                        'id'   => $this->bookingItem->product->id,
                        'name' => $this->bookingItem->product->name,
                    ]
                    : null,
            ] : null),
        ];
    }
}
