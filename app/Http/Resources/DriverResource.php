<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
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
            'name' => $this->name,
            'contact' => $this->contact,
            'vendor_name' => $this->vendor_name,
            'profile' => asset('storage/images/driver/' . $this->profile),
            'car_photo' => asset('storage/images/driver/' . $this->car_photo),
            'supplier' => [
                'id' => $this->supplier->id,
                'contact' => $this->supplier->contact,
                'logo' => asset('storage/images/supplier/' . $this->supplier->logo),
                'bank_name' => $this->supplier->bank_name,
                'bank_account_no' => $this->supplier->bank_account_no,
                'bank_account_name' => $this->supplier->bank_account_name,
            ],
        ];
    }
}
