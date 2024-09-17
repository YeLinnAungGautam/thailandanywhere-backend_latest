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
        $new_drivers = $this->drivers->map(function ($driver) {
            $driver->profile = asset('storage/images/driver/' . $driver->profile);
            $driver->car_photo = asset('storage/images/driver/' . $driver->car_photo);

            return $driver;
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'contact' => $this->contact,
            'logo' => asset('storage/images/supplier/' . $this->logo),
            'bank_name' => $this->bank_name,
            'bank_account_no' => $this->bank_account_no,
            'bank_account_name' => $this->bank_account_name,
            'drivers' => $new_drivers,
        ];
    }
}
