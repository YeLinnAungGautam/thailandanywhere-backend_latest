<?php

namespace App\Http\Resources\BookingItem;

use App\Services\BookingItemDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemGroupListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = [
            'id' => $this->id,
            'product_type' => class_basename($this->product_type),
            'total_cost_price' => $this->total_cost_price,
            'reservation_count' => $this->bookingItems->count(),
            'booking_crm_id' => $this->booking->crm_id ?? null,
            'product_name' => $this->bookingItems->first()->product->name ?? 'N/A',
            'customer_name' => $this->booking->customer->name ?? 'N/A',

            'total_amount' => $this->bookingItems->sum('amount'),
            'expense_amount' => BookingItemDataService::getTotalExpenseAmount($this->bookingItems()),

            'latest_service_date' => Carbon::parse($this->bookingItems->max('service_date'))->format('M d, Y') ?? 'N/A',

            'customer_payment_status' => $this->booking->payment_status ?? 'not_paid',
            'expense_status' => $this->calculateGroupExpenseStatus(),

            'items' => $this->transformedItems(),
        ];

        return $result;
    }

    private function transformedItems()
    {
        return $this->bookingItems->map(function ($item) {
            return [
                'id' => $item->id,
                'product_name' => $item->product->name ?? 'N/A',
                'variant_name' => $item->acsr_variation_name ?? 'N/A',
                'reservation_status' => $item->reservation_status,
                'expense_status' => $item->payment_status,
                'booking_status' => $this->booking->payment_status ?? 'not_paid',
            ];
        })->all();
    }

    protected function calculateGroupExpenseStatus()
    {
        $hasFullyPaid = $this->bookingItems->contains('payment_status', 'fully_paid');

        $hasNotPaid = $this->bookingItems->contains('payment_status', 'not_paid');

        if ($hasFullyPaid && $hasNotPaid) {
            return 'partially_paid';
        }
        if ($hasNotPaid && !$hasFullyPaid) {
            return 'not_paid';
        }

        return 'fully_paid';
    }
}
