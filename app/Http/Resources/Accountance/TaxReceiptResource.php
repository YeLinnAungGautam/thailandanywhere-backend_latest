<?php

namespace App\Http\Resources\Accountance;

use App\Http\Resources\Accountance\Detail\ProductResource;
use App\Http\Resources\Accountance\BookingItemResource; // Missing import
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tax_credit_id' => $this->invoice_number,
            'groups' => $this->getAllBookingItems(),
            'product' => new ProductResource($this->whenLoaded('product')), // Use whenLoaded
            'product_type' => $this->getProductType(),
            'service_start_date' => $this->service_start_date?->format('Y-m-d'), // Safe formatting
            'service_end_date' => $this->service_end_date?->format('Y-m-d'), // Safe formatting
            'total_tax_withold' => $this->total_tax_withold,
            'total_tax_amount' => $this->total_tax_amount,
            'total_after_tax' => $this->total_after_tax,
            'all_transactions' => $this->getAllExpensesMetaDates(),
        ];
    }

    /**
     * Get all booking items from all groups
     */
    private function getAllBookingItems()
    {
        // Check if groups are loaded and not null
        if (!$this->relationLoaded('groups') || !$this->groups) {
            return collect([]);
        }

        // Flatten all booking items from all groups
        $allBookingItems = $this->groups->flatMap(function ($group) {
            // Check if bookingItems relationship exists and is loaded
            return $group->bookingItems ?? collect([]);
        });

        return BookingItemResource::collection($allBookingItems);
    }

    /**
     * Get all expense receipts from customer documents
     */
    private function getAllExpensesMetaDates()
    {
        // Check if groups are loaded and not null
        if (!$this->relationLoaded('groups') || !$this->groups) {
            return [];
        }

        $metaDates = [];

        $this->groups->each(function ($group) use (&$metaDates) {
            if ($group->customerDocuments) {
                $expenseReceipts = $group->customerDocuments->where('type', 'expense_receipt');

                foreach ($expenseReceipts as $receipt) {
                    // Extract only the date from meta
                    if (isset($receipt->meta['date']) && !empty($receipt->meta['date'])) {
                        $metaDates[] = $receipt->meta['date'];
                    }
                }
            }
        });

        return $metaDates; // Return simple array of date strings
    }

    /**
     * Get properly formatted product type
     */
    private function getProductType()
    {
        switch ($this->product_type) {
            case 'App\Models\EntranceTicket':
                return 'entrance_ticket';
            case 'App\Models\Hotel':
                return 'hotel';
            default:
                // Handle other cases or return as-is
                return strtolower(str_replace(['App\Models\\', '\\'], ['', '_'], $this->product_type));
        }
    }
}
