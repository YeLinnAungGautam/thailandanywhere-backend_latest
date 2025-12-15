<?php

namespace App\Http\Resources\BookingItem;

use App\Services\BookingItemDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr\Cast\Object_;

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
            'total_cost_price' => $this->totalExpenseAmount(),
            'reservation_count' => $this->bookingItems->count(),
            'booking_crm_id' => $this->booking->crm_id ?? null,
            'product_name' => $this->bookingItems->first()->product->name ?? 'N/A',
            'customer_name' => $this->booking->customer->name ?? 'N/A',
            'have_tax_receipt' => $this->taxReceipts()->count() > 0 ? true : false,
            'sent_booking_request' => $this->sent_booking_request,
            'sent_expense_mail' => $this->sent_expense_mail,
            'total_amount' => $this->bookingItems->sum('amount'),
            'expense_amount' => BookingItemDataService::getTotalExpenseAmount($this->bookingItems()),

            'latest_service_date' => Carbon::parse($this->bookingItems->max('checkout_date'))->format('M d, Y') ?? 'N/A',
            'firstest_service_date' => Carbon::parse($this->bookingItems->min('service_date'))->format('M d, Y') ?? 'N/A',

            'customer_payment_status' => $this->booking->payment_status ?? 'not_paid',
            'expense_status' => $this->calculateGroupExpenseStatus(),

            'items' => $this->transformedItems(),
            'has_booking_confirm_letter' => $this->hasBookingConfirmLetter(),
            'has_passport' => $this->hasPassport(),
            'has_confirm_letter' => $this->hasConfirmLetter(),
            'booking_items_payment_detail' => $this->product_type === 'App\Models\PrivateVanTour' ? $this->bookingItemsPaymentDetail() : false,
            'booking_items_assigned' =>  $this->product_type === 'App\Models\PrivateVanTour' ? $this->bookingItemsAssigned() : false,
            'booking_request_proof' =>$this->bookingRequestProof(),

            'booking_email_sent_date' => $this->booking_email_sent_date,
            'expense_email_sent_date' => $this->expense_email_sent_date,
            'invoice_mail_sent_date' => $this->invoice_mail_sent_date,
            'have_invoice_mail' => $this->have_invoice_mail,
            'invoice_mail_proof' => $this->haveInvoiceMailProof(),
        ];

        return $result;
    }

    private function transformedItems()
    {
        return $this->bookingItems->map(function ($item) {
            $data = [
                'id' => $item->id,
                'product_name' => $item->product->name ?? 'N/A',
                'variant_name' => $item->acsr_variation_name ?? 'N/A',
                'reservation_status' => $item->reservation_status,
                'expense_status' => $item->payment_status,
                'booking_status' => $this->booking->payment_status ?? 'not_paid',
                'service_date' => Carbon::parse($item->service_date)->format('M d') ?? 'N/A',
                'quantity' => $item->quantity,
                'is_allowment_have' => $item->is_allowment_have,
            ];

            $individualPricing = $item->individual_pricing ?
                (is_string($item->individual_pricing) ? json_decode($item->individual_pricing) : $item->individual_pricing) :
                null;

            if ($item->product_type === 'App\Models\Hotel') {
                $data['days'] = $item->checkin_date ? Carbon::parse($item->checkout_date)->diffInDays(Carbon::parse($item->checkin_date)) : 'N/A';
                $data['checkin_date'] = $item->checkin_date;
                $data['checkout_date'] = $item->checkout_date;
            }

            if ($item->product_type === 'App\Models\EntranceTicket') {
                $data['child_quantity'] = $individualPricing['child']['quantity'] ?? 0;
            }
            return $data;
        })->all();
    }

    // private function haveTaxReceipt()
    // {
    //     return $this->taxReceipts();
    // }

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

    protected function totalExpenseAmount(){
        return $this->bookingItems->sum('total_cost_price');
    }

    private function hasBookingConfirmLetter()
    {
        return $this->customerDocuments->contains('type', 'booking_confirm_letter');
    }

    private function hasPassport()
    {
        return $this->customerDocuments->contains('type', 'passport');
    }

    private function hasConfirmLetter()
    {
        return $this->customerDocuments->contains('type', 'confirmation_letter');
    }

    private function bookingRequestProof()
    {
        return $this->customerDocuments->contains('type', 'booking_request_proof');
    }

    private function haveInvoiceMailProof()
    {
        return $this->customerDocuments->contains('type', 'invoice_mail_proof');
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
