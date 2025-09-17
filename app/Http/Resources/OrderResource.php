<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'user' => $this->user,
            'customer' => new CustomerResource($this->customer),
            'admin' => $this->admin,
            'order_number' => $this->order_number,
            'sold_from' => $this->sold_from,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'order_datetime' => $this->order_datetime,
            'formatted_order_datetime' => $this->order_datetime ? Carbon::parse($this->order_datetime)->format('d M Y, H:i A') : null,
            'expire_datetime' => $this->expire_datetime,
            'formatted_expire_datetime' => $this->expire_datetime ? Carbon::parse($this->expire_datetime)->format('d M Y, H:i A') : null,
            'balance_due_date' => $this->balance_due_date,
            'formatted_balance_due_date' => $this->balance_due_date ? Carbon::parse($this->balance_due_date)->format('d M Y') : null,
            'order_status' => $this->order_status,
            'booking_id' => $this->booking_id,
            'discount' => $this->discount,
            'sub_total' => $this->sub_total,
            'grand_total' => $this->grand_total,
            'deposit_amount' => $this->deposit_amount,

            'comment' => $this->comment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // 'items' => $this->items,
            'item_count' => $this->items ? $this->items->count() : 0,
            'items' => OrderItemResource::collection($this->items) ?? [],
            'payments' => OrderPaymentResource::collection($this->payments) ?? [],
            // 'payments' => OrderPaymentResource::collection($this->payments) ?? [],
        ];
    }
}
