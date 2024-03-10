<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopSellingProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'crm_id' => $this->crm_id,
            'selling_price' => $this->selling_price,
            'quantity' => $this->quantity,
            'total_amount' => $this->calcSalePrice,
            'product_type_name' => $this->acsr_product_type_name,
            'product_name' => $this->product->name,
            'product_type' => $this->product_type,
            'variation_name' => $this->acsr_variation_name
        ];
    }
}
