<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $baseData = [
                'id' => $this->id,
                'related_id' => $this->related_id,
                'case_type' => $this->case_type,
                'name' => $this->name,
                'detail' => $this->detail,
                'verification_status' => $this->verification_status,
                'created_at' => $this->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            ];

        return $baseData;

    }
}
