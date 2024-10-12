<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'phone_number' => $this->phone_number,
            'nrc_number' => $this->nrc_number,
            'email' => $this->email,
            'dob' => $this->dob,
            'line_id' => $this->line_id,
            'is_corporate_customer' => $this->is_corporate_customer,
            'photo' => $this->photo ? Storage::url('images/' . $this->photo) : null,
            'comment' => $this->comment,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];

        if ($request->with_sale_record) {
            $data['sales'] = $this->bookings;
        }

        return $data;
    }
}
