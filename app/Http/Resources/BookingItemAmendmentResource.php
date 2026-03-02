<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemAmendmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Decode snapshot once for reuse
        $snapshot = null;
        if ($this->item_snapshot) {
            $snapshot = is_string($this->item_snapshot)
                ? json_decode($this->item_snapshot, true)
                : (array) $this->item_snapshot;
        }

        $item = $this->bookingItem;

        // When booking item is deleted, booking_item_id becomes null
        // and the relation returns an empty/null model — use snapshot instead
        $useSnapshot = $snapshot !== null && ($item === null || $item->id === null);

        if ($useSnapshot) {
            $bookingItemData = [
                'id'               => $snapshot['id'] ?? null,
                'booking_id'       => $snapshot['booking_id'] ?? null,
                'crm_id'           => $snapshot['crm_id'] ?? null,
                'product_type'     => $snapshot['product_type'] ?? null,
                'product_id'       => $snapshot['product_id'] ?? null,
                'service_date'     => $snapshot['service_date'] ?? null,
                'checkout_date'    => $snapshot['checkout_date'] ?? null,
                'quantity'         => $snapshot['quantity'] ?? null,
                'selling_price'    => $snapshot['selling_price'] ?? null,
                'cost_price'       => $snapshot['cost_price'] ?? null,
                'amount'           => $snapshot['amount'] ?? null,
                'total_cost_price' => $snapshot['total_cost_price'] ?? null,
                'discount'         => $snapshot['discount'] ?? null,
                'days'             => $snapshot['days'] ?? null,
                'individual_pricing' => $snapshot['individual_pricing'] ?? null,
                'product'          => isset($snapshot['product']) ? [
                    'id'   => $snapshot['product']['id'] ?? null,
                    'name' => $snapshot['product']['name'] ?? null,
                ] : null,
                'customer_info'    => isset($snapshot['customer_info']) ? [
                    'name' => $snapshot['customer_info']['name'] ?? null,
                ] : null,
                'booking'          => isset($snapshot['booking']) ? [
                    'id'           => $snapshot['booking']['id'] ?? null,
                    'booking_date' => $snapshot['booking']['booking_date'] ?? null,
                    'is_inclusive' => $snapshot['booking']['is_inclusive'] ?? null,
                ] : null,
            ];
        } elseif ($item && $item->id !== null) {
            $bookingItemData = [
                'id'               => $item->id,
                'booking_id'       => $item->booking_id,
                'crm_id'           => $item->crm_id,
                'product_type'     => $item->product_type,
                'product_id'       => $item->product_id,
                'service_date'     => $item->service_date,
                'checkout_date'    => $item->checkout_date,
                'quantity'         => $item->quantity,
                'selling_price'    => $item->selling_price,
                'cost_price'       => $item->cost_price,
                'amount'           => $item->amount,
                'total_cost_price' => $item->total_cost_price,
                'discount'         => $item->discount,
                'days'             => $item->days,
                'individual_pricing' => $item->individual_pricing,
                'product'          => $item->product ? [
                    'id'   => $item->product->id,
                    'name' => $item->product->name,
                ] : null,
                'customer_info'    => $item->booking?->customer ? [
                    'name' => $item->booking->customer->name,
                ] : null,
                'booking'          => $item->booking ? [
                    'id'           => $item->booking->id,
                    'booking_date' => $item->booking->booking_date,
                    'is_inclusive' => $item->booking->is_inclusive,
                ] : null,
            ];
        } else {
            $bookingItemData = null;
        }

        return [
            'id'              => $this->id,
            'booking_item_id' => $this->booking_item_id ?? ($snapshot['id'] ?? null),
            'booking_item'    => $bookingItemData,
            'amend_history'   => $this->amend_history,
            'amend_request'   => $this->amend_request,
            'amend_mail_sent' => $this->amend_mail_sent,
            'amend_approve'   => $this->amend_approve,
            'amend_status'    => $this->amend_status,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
