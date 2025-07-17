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

            // New fields for BookingItemGroup
            'has_invoice' => $this->when(
                $request->input('include_relatable') && $this->relatable_type === 'App\Models\BookingItemGroup',
                $this->hasInvoice()
            ),
            'tax_receipts' => $this->when(
                $request->input('include_relatable') && $this->relatable_type === 'App\Models\BookingItemGroup',
                $this->getTaxReceipts()
            ),

            // Remove these expensive operations from list view
            // 'relatable' => $relatable,
            // 'grouped_items' => $groupedItems,
            // 'product_type' => $this->getProductType(),
            'product_type' => $this->when($request->input('include_relatable'), $this->getProductType()),
        ];
    }

    /**
     * Check if BookingItemGroup has invoice (customer_documents with type 'booking_confirm_letter')
     */
    public function hasInvoice()
    {
        if ($this->relatable_type !== 'App\Models\BookingItemGroup' || !$this->relatable_id) {
            return false;
        }

        try {
            // Check if relatable and customer_documents are loaded
            if ($this->relationLoaded('relatable') &&
                $this->relatable &&
                $this->relatable->relationLoaded('customer_documents')) {

                return $this->relatable->customer_documents
                    ->where('type', 'booking_confirm_letter')
                    ->isNotEmpty();
            }

            // If not loaded, query database directly
            $hasInvoice = \App\Models\CustomerDocument::where('booking_item_group_id', $this->relatable_id)
                ->where('type', 'booking_confirm_letter')
                ->exists();

            return $hasInvoice;
        } catch (\Exception $e) {
            Log::error("Error checking invoice for BookingItemGroup {$this->relatable_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get tax receipts for BookingItemGroup
     */
    public function getTaxReceipts()
    {
        if ($this->relatable_type !== 'App\Models\BookingItemGroup' || !$this->relatable_id) {
            return [];
        }

        try {
            // Check if relatable and taxReceipts are loaded
            if ($this->relationLoaded('relatable') &&
                $this->relatable &&
                $this->relatable->relationLoaded('taxReceipts')) {

                return $this->relatable->taxReceipts->map(function ($taxReceipt) {
                    return [
                        'id' => $taxReceipt->id,
                        'pivot_id' => $taxReceipt->pivot->id ?? null,
                        'created_at' => $taxReceipt->pivot->created_at ?? null,
                        'updated_at' => $taxReceipt->pivot->updated_at ?? null,
                        // Add other tax receipt fields you need
                    ];
                });
            }

            // If not loaded, query database directly
            $taxReceipts = \App\Models\TaxReceipt::join('tax_receipt_groups', 'tax_receipts.id', '=', 'tax_receipt_groups.tax_receipt_id')
                ->where('tax_receipt_groups.booking_item_group_id', $this->relatable_id)
                ->select([
                    'tax_receipts.*',
                    'tax_receipt_groups.id as pivot_id',
                    'tax_receipt_groups.created_at',
                    'tax_receipt_groups.updated_at',
                    // Add other fields you need from tax_receipts table
                ])
                ->get()
                ->map(function ($taxReceipt) {
                    return [
                        'detail' => $taxReceipt,
                        'image' => $taxReceipt->receipt_image ? Storage::url('images/' . $taxReceipt->receipt_image) : null
                    ];
                });

            return $taxReceipts;
        } catch (\Exception $e) {
            Log::error("Error getting tax receipts for BookingItemGroup {$this->relatable_id}: " . $e->getMessage());
            return [];
        }
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
     * Get total amount
     */
    public function getProductType()
    {
        if($this->relatable_type == 'App\Models\BookingItemGroup') {
            return $this->relatable->bookingItems->first()->product_type ?? null;
        }
        return null;
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
