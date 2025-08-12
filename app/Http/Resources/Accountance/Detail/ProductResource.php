<?php

namespace App\Http\Resources\Accountance\Detail;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->legal_name,
            'vat_id' => $this->vat_id,
            'vat_name' => $this->vat_name,
            'vat_address' => $this->vat_address,
        ];
    }
}
