<?php

namespace App\Http\Resources\BookingItem;

use App\Http\Resources\Accountance\CashImageResource;
use App\Http\Resources\BookingItemResource;
use App\Http\Resources\BookingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemGroupDetailResource extends JsonResource
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
            'product_type' => class_basename($this->product_type),
            'total_cost_price' => $this->totalExpenseAmount(),
            'reservation_count' => $this->bookingItems->count(),
            'booking_crm_id' => $this->booking->crm_id ?? null,
            'product_name' => $this->bookingItems->first()->product->name ?? 'N/A',
            'customer_name' => $this->booking->customer->name ?? 'N/A',
            'sent_booking_request' => $this->sent_booking_request,
            'sent_expense_mail' => $this->sent_expense_mail,
            'expense_method' => $this->expense_method,
            'expense_bank_name' => $this->expense_bank_name,
            'expense_bank_account' => $this->expense_bank_account,
            'expense_status' => $this->calculateGroupExpenseStatus(),
            'expense_total_amount' => $this->expense_total_amount,
            'confirmation_status' => $this->confirmation_status,
            'confirmation_code' => $this->confirmation_code,
            'booking' => BookingResource::make($this->booking),
            'items' => BookingItemResource::collection($this->bookingItems),
            'has_booking_confirm_letter' => $this->hasBookingConfirmLetter(),
            'has_passport' => $this->hasPassport(),
            'has_confirm_letter' => $this->hasConfirmLetter(),
            'booking_items_payment_detail' => $this->product_type === 'App\Models\PrivateVanTour' ? $this->bookingItemsPaymentDetail() : false,
            'booking_items_assigned' => $this->product_type === 'App\Models\PrivateVanTour' ? $this->bookingItemsAssigned() : false,
            'expense' => $this->cashImages ? CashImageResource::collection($this->cashImages) : [],
        ];
    }

    private function hasBookingConfirmLetter()
    {
        return $this->customerDocuments->contains('type', 'booking_confirm_letter');
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

    private function hasPassport()
    {
        return $this->customerDocuments->contains('type', 'passport');
    }

    private function hasConfirmLetter()
    {
        return $this->customerDocuments->contains('type', 'confirmation_letter');
    }

    protected function totalExpenseAmount(){
        return $this->bookingItems->sum('total_cost_price');
    }

    private function bookingItemsPaymentDetail()
    {
        foreach ($this->bookingItems as $item) {
            if ($item->is_driver_collect === null) {
                return false;
            }
        }

        return true;
    }

    private function bookingItemsAssigned()
    {
        foreach ($this->bookingItems as $item) {
            if ($item->reservationCarInfo?->supplier_id === null && $item->reservationCarInfo?->driver_id === null) {
                return false;
            }
        }

        return true;
    }
}
