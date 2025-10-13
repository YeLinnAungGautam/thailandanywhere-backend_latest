<?php

namespace App\Http\Resources\Accountance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CashImageResource extends JsonResource
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
            'image' => $this->image ? Storage::url('images/' . $this->image) : null,
            'date' => $this->date ? $this->date->format('d-m-Y H:i:s') : null,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
            'sender' => $this->sender,
            'reciever' => $this->receiver,
            'receiver' => $this->receiver,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'interact_bank' => $this->interact_bank,
            'relatable_type' => $this->relatable_type,
            'relatable_id' => $this->relatable_id,
            'relatables' => $this->relatables,

            // Related imageables through pivot table
            'related_bookings' => $this->whenLoaded('cashBookings', function () {
                return $this->cashBookings->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'crm_id' => $booking->crm_id,
                        'customer_name' => $booking->customer?->name,
                        'grand_total' => $booking->grand_total,
                        'pivot' => [
                            'type' => $booking->pivot->type,
                            'deposit' => $booking->pivot->deposit,
                            'notes' => $booking->pivot->notes,
                            'created_at' => $booking->pivot->created_at,
                            'updated_at' => $booking->pivot->updated_at,
                        ]
                    ];
                });
            }),

            'related_booking_item_groups' => $this->whenLoaded('cashBookingItemGroups', function () {
                return $this->cashBookingItemGroups->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'booking_id' => $group->booking_id,
                        'product_type' => $group->product_type,
                        'product_id' => $group->product_id,
                        'total_cost_price' => $group->total_cost_price,
                        'pivot' => [
                            'type' => $group->pivot->type,
                            'deposit' => $group->pivot->deposit,
                            'notes' => $group->pivot->notes,
                            'created_at' => $group->pivot->created_at,
                            'updated_at' => $group->pivot->updated_at,
                        ]
                    ];
                });
            }),

            'related_cash_books' => $this->whenLoaded('cashBooks', function () {
                return $this->cashBooks->map(function ($cashBook) {
                    return [
                        'id' => $cashBook->id,
                        'title' => $cashBook->title ?? null,
                        'description' => $cashBook->description ?? null,
                        'amount' => $cashBook->amount ?? null,
                        'pivot' => [
                            'type' => $cashBook->pivot->type,
                            'deposit' => $cashBook->pivot->deposit,
                            'notes' => $cashBook->pivot->notes,
                            'created_at' => $cashBook->pivot->created_at,
                            'updated_at' => $cashBook->pivot->updated_at,
                        ]
                    ];
                });
            }),
        ];
    }
}
