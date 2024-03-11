<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopSellingProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_type' => $this->product_type,
            'product_name' => $this->product->name,
            'variation_name' => $this->acsr_variation_name,
            'price' => $this->getProductPrice(),
            'quantity' => $this->total_quantity,
            'total_amount' => $this->getProductPrice() * $this->total_quantity,
            'product_type_name' => $this->acsr_product_type_name,
        ];
    }

    private function getProductPrice()
    {
        return explode(',', $this->selling_prices)[0] ?? 0;
    }
}
