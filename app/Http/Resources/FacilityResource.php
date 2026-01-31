<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FacilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // 'image' => asset('storage/images/facility/' . $this->image),
            'image' => $this->image ? Storage::url('images/facility/' . $this->image) : null,
            'icon' => $this->icon, // ✅ Added icon
            'order' => $this->order, // ✅ Added order
            'is_active' => $this->is_active, // ✅ Added is_active
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
