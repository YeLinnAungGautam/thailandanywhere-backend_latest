<?php

namespace App\Http\Resources\Accountance;

use App\Http\Resources\BookingItemGroupResource;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BookingItemGroup\CustomerDocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CashImageListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Option 1: Minimal data for list view (FASTEST)
        return [
            'id' => $this->id,
            'image' => $this->whenLoaded('image', function() {
                return $this->image ? Storage::url('images/' . $this->image) : null;
            }),
            'date' => $this->date ? $this->formatDate($this->date) : null,
            'created_at' => $this->created_at ? $this->formatDate($this->created_at) : null,
            'updated_at' => $this->updated_at ? $this->formatDate($this->updated_at) : null,
            'sender' => $this->sender,
            'receiver' => $this->receiver,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'interact_bank' => $this->interact_bank,

            // Only include relatable data if explicitly requested
            'relatable_type' => $this->when($request->input('include_relatable'), $this->relatable_type),
            'relatable_id' => $this->when($request->input('include_relatable'), $this->relatable_id),
            'crm_id' => $this->when($request->input('include_relatable'), $this->relatable->crm_id ?? null),

            // Remove these expensive operations from list view
            // 'relatable' => $relatable,
            // 'grouped_items' => $groupedItems,
            'product_type' => $this->getProductType(),
        ];
    }

    public function getProductType()
    {
        // Only get product_type if relatable_type is BookingItemGroup
        if ($this->relatable_type === 'App\Models\BookingItemGroup') {
            // Check if relatable relationship is loaded
            if ($this->relationLoaded('relatable') && $this->relatable) {
                return $this->relatable->product_type ?? null;
            }

            // If not loaded, we can load just this field efficiently
            if ($this->relatable_id) {
                try {
                    $group = \App\Models\BookingItemGroup::select('product_type')
                        ->where('id', $this->relatable_id)
                        ->first();

                    return $group ? $group->product_type : null;
                } catch (\Exception $e) {
                    Log::error("Error fetching product_type for BookingItemGroup {$this->relatable_id}: " . $e->getMessage());
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Format date consistently
     */
    protected function formatDate($date)
    {
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }
        return $date->format('d-m-Y H:i:s');
    }
}


