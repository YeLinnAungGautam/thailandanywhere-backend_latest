<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'logo' => asset('storage/images/supplier/' . $this->logo),
            'bank_name' => $this->bank_name,
            'bank_account_no' => $this->bank_account_no,
            'bank_account_name' => $this->bank_account_name,
            'driver' => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'contact' => $this->driver->contact,
                'vendor_name' => $this->driver->vendor_name,
                'profile' => asset('storage/images/driver/' . $this->driver->profile),
                'car_photo' => asset('storage/images/driver/' . $this->driver->car_photo),
            ],
        ];
    }
}
