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
            'image' => $this->image ? Storage::url('images/' . $this->image) : null,
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
            'crm_id' => $this->when($request->input('include_relatable'), $this->getCrmId()),

            // VAT calculation based on relatable_type
            'vat' => $this->when($request->input('include_relatable'), $this->calculateVat()),
            'net_vat' => $this->when($request->input('include_relatable'), $this->calculateNetVat()),
            'commission' => $this->when($request->input('include_relatable'), $this->getCommission()),

            // Remove these expensive operations from list view
            // 'relatable' => $relatable,
            // 'grouped_items' => $groupedItems,
            'product_type' => $this->getProductType(),
        ];
    }

    /**
     * Calculate VAT based on relatable_type
     */
    public function calculateVat()
    {
        if (!$this->relatable_type || !$this->relatable_id) {
            return null;
        }

        try {
            if ($this->relatable_type === 'App\Models\Booking') {
                // For Booking: use output_vat directly
                if ($this->relationLoaded('relatable') && $this->relatable) {
                    return $this->relatable->output_vat ?? 0;
                }

                // If not loaded, fetch just the output_vat
                $booking = \App\Models\Booking::select('output_vat')
                    ->where('id', $this->relatable_id)
                    ->first();

                return $booking ? ($booking->output_vat ?? 0) : 0;

            } elseif ($this->relatable_type === 'App\Models\BookingItemGroup') {
                // For BookingItemGroup: calculate VAT from bookingItems total_cost_price
                return $this->calculateBookingItemGroupVat();
            }

            return 0;
        } catch (\Exception $e) {
            Log::error("Error calculating VAT for CashImage {$this->id}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate VAT for BookingItemGroup
     */
    protected function calculateBookingItemGroupVat()
    {
        try {
            // Check if relatable and bookingItems are loaded
            if ($this->relationLoaded('relatable') &&
                $this->relatable &&
                $this->relatable->relationLoaded('bookingItems')) {

                $sumTotalCostPrice = $this->relatable->bookingItems->sum('total_cost_price');
            } else {
                // If not loaded, fetch sum directly from database
                $sumTotalCostPrice = \App\Models\BookingItem::where('group_id', $this->relatable_id)
                    ->sum('total_cost_price');
            }

            // Calculate VAT: sum_total_cost_price - (sum_total_cost_price / 1.07)
            if ($sumTotalCostPrice > 0) {
                return $sumTotalCostPrice - ($sumTotalCostPrice / 1.07);
            }

            return 0;
        } catch (\Exception $e) {
            Log::error("Error calculating BookingItemGroup VAT for group {$this->relatable_id}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate Net VAT
     */
    public function calculateNetVat()
    {
        if (!$this->relatable_type || !$this->relatable_id) {
            return null;
        }

        try {
            if ($this->relatable_type === 'App\Models\Booking') {
                // For Booking: commission - (commission / 1.07)
                $commission = $this->getCommission();
                if ($commission > 0) {
                    return $commission - ($commission / 1.07);
                }
                return 0;

            } elseif ($this->relatable_type === 'App\Models\BookingItemGroup') {
                // For BookingItemGroup: use the same VAT calculation as calculateVat()
                return 0;
            }

            return 0;
        } catch (\Exception $e) {
            Log::error("Error calculating Net VAT for CashImage {$this->id}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get commission value
     */
    public function getCommission()
    {
        if (!$this->relatable_type || !$this->relatable_id) {
            return null;
        }

        try {
            if ($this->relatable_type === 'App\Models\Booking') {
                if ($this->relationLoaded('relatable') && $this->relatable) {
                    return $this->relatable->commission ?? 0;
                }

                // If not loaded, fetch just the commission
                $booking = \App\Models\Booking::select('commission')
                    ->where('id', $this->relatable_id)
                    ->first();

                return $booking ? ($booking->commission ?? 0) : 0;
            }

            // For BookingItemGroup, commission might not be applicable
            return 0;
        } catch (\Exception $e) {
            Log::error("Error getting commission for CashImage {$this->id}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get CRM ID
     */
    public function getCrmId()
    {
        if (!$this->relatable_type || !$this->relatable_id) {
            return null;
        }

        try {
            if ($this->relationLoaded('relatable') && $this->relatable) {
                return $this->relatable->crm_id ?? null;
            }

            // If not loaded, fetch just the crm_id
            if ($this->relatable_type === 'App\Models\Booking') {
                $booking = \App\Models\Booking::select('crm_id')
                    ->where('id', $this->relatable_id)
                    ->first();
                return $booking ? $booking->crm_id : null;

            } elseif ($this->relatable_type === 'App\Models\BookingItemGroup') {
                // BookingItemGroup might not have crm_id directly,
                // you might need to get it from the related booking
                $group = \App\Models\BookingItemGroup::select('booking_id')
                    ->where('id', $this->relatable_id)
                    ->first();

                if ($group && $group->booking_id) {
                    $booking = \App\Models\Booking::select('crm_id')
                        ->where('id', $group->booking_id)
                        ->first();
                    return $booking ? $booking->crm_id : null;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Error getting CRM ID for CashImage {$this->id}: " . $e->getMessage());
            return null;
        }
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
