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
            'total_unverify_amount' => $this->total_unverify_amount,
            'total_cost_price' => $this->total_cost_price,
            'total_unverify_cost_price' => $this->total_unverify_cost_price,
            'connection_detail' => $this->connection_detail,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
