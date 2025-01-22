<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class InclusivePdfResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        {
            // return parent::toArray($request);
            return [
                'id' => $this->id,
                'pdf' => $this->pdf_path ? Storage::url('inclusive_pdfs/' . $this->pdf_path) : null,
                'download_link' => $this->pdf_path
                ? url('/api/download-pdf/' . $this->id) // Optional custom route for secure downloads
                : null,
                'created_at' => $this->created_at->format('d-m-Y H:i:s'),
                'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
            ];
        }
    }
}
