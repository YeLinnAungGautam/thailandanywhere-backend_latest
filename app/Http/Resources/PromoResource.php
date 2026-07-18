<?php

namespace App\Http\Resources;

use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PromoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'promo_id'   => $this->promo_id,
            'promo_name' => $this->promo_name,
            'promo_des'  => $this->promo_des,
            'promo_code' => $this->promo_code,

            'promo_type'   => $this->promo_type, // fixed | percent
            'promo_amount' => (float) $this->promo_amount,

            'promo_count'       => $this->promo_count,
            'promo_used_count'  => $this->promo_used_count,
            'promo_remaining'   => max(0, $this->promo_count - $this->promo_used_count),

            'promo_active'      => (bool) $this->promo_active,
            'promo_start_date'  => $this->promo_start_date?->format('Y-m-d H:i:s'),
            'promo_end_date'    => $this->promo_end_date?->format('Y-m-d H:i:s'),
            'formatted_start_date' => $this->promo_start_date?->format('d M Y'),
            'formatted_end_date'   => $this->promo_end_date?->format('d M Y'),

            // computed status flags - handy for frontend without re-deriving logic
            'is_expired'   => $this->promo_end_date ? now()->gt($this->promo_end_date) : false,
            'is_upcoming'  => $this->promo_start_date ? now()->lt($this->promo_start_date) : false,
            'is_valid'     => $this->isValid(),

            'promo_applies_to'    => $this->promo_applies_to, // all | specific
            'applicable_products' => $this->when(
                $this->promo_applies_to === 'specific',
                fn () => $this->formatApplicableProducts()
            ),

            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i:s'),

            'usages_count' => $this->whenCounted('usages'),
            'usages' => PromoUsageResource::collection($this->whenLoaded('usages')),
            'image' => $this->image ? Storage::url('images/' . $this->image) : null,
        ];
    }

    /**
     * Turn the raw applicable_products JSON (keyed by internal friendly name)
     * into a clean, readable structure for the frontend.
     */
    private function formatApplicableProducts(): array
    {
        $raw = $this->applicable_products ?? [];
        $formatted = [];

        foreach (Promo::PRODUCT_TYPES as $key => $modelClass) {
            if (! isset($raw[$key])) {
                continue;
            }

            $formatted[$key] = [
                'mode'        => $raw[$key] === 'all' ? 'all' : 'specific_ids',
                'product_ids' => $raw[$key] === 'all' ? [] : $raw[$key],
            ];
        }

        return $formatted;
    }
}
