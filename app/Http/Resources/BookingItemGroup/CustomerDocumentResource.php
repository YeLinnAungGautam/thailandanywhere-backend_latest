<?php

namespace App\Http\Resources\BookingItemGroup;

use App\Models\CustomerDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CustomerDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $file_path = $this->file ? Storage::url(CustomerDocument::specificFolderPath($this->type) . '/' . $this->file) : null;

        return [
            'id' => $this->id,
            'booking_item_group_id' => $this->booking_item_group_id,
            'type' => $this->type,
            'file' => $file_path,
            'file_name' => $this->file_name,
            'mine_type' => $this->mine_type,
            'file_size' => $this->file_size,
            'meta' => $this->meta,
        ];
    }
}
