<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChartOfAccountResource extends JsonResource
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
            'account_code' => $this->account_code,
            'account_name' => $this->account_name,
            'account_class' => new AccountClassResource($this->accountClass),
            'account_head' => new AccountHeadResource($this->accountHead),
            'product_type' => $this->product_type,
            'connection' => $this->connection,
            'total_amount' => $this->total_amount,
            'verified_amount' => $this->verified_amount,
            'unverified_amount' => $this->unverified_amount,
            'pending_amount' => $this->pending_amount,
            // cost
            'total_cost_price' => $this->total_cost_amount,
            'verified_cost_price' => $this->verified_cost_price,
            'unverified_cost_price' => $this->unverified_cost_price,
            'pending_cost_price' => $this->pending_cost_price,
            'connection_detail' => $this->connection_detail,

            // Account code specific calculations (1-3000-01, 1-3000-02, 1-3000-03)
            // Overdue balance due total (balance_due_date overdue and payment_status not_paid)
            'over_balance_due_total' => $this->over_balance_due_total ?? null,

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
