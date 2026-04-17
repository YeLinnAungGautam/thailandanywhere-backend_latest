<?php

namespace App\Http\Resources;

use App\Http\Resources\Accountance\CashImageResource;
use App\Services\BookingItemSnapshotService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BookingItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshotService = new BookingItemSnapshotService();

        // ---- Parse variation_snapshot ----
        $variationSnapshot = null;
        if ($this->variation_snapshot) {
            $variationSnapshot = is_string($this->variation_snapshot)
                ? json_decode($this->variation_snapshot, true)
                : $this->variation_snapshot;
        }

        // ---- Parse product_snapshot ----
        $productSnapshot = null;
        if ($this->product_snapshot) {
            $productSnapshot = is_string($this->product_snapshot)
                ? json_decode($this->product_snapshot, true)
                : $this->product_snapshot;
        }

        $product     = null;
        $stay_nights = null;
        $resolvedRoom      = null;
        $resolvedCar       = null;
        $resolvedVariation = null;
        $resolvedTicket    = null;

        switch ($this->product_type) {
            case 'App\Models\Hotel':
                // product: snapshot first, then live
                $product =  $this->product ? new HotelResource($this->product) : $productSnapshot ?? null;
                // variation: snapshot first, then live room
                $resolvedRoom = $variationSnapshot ?? ($this->room ?? null);
                $stay_nights  = Carbon::parse($this->checkin_date)->diffInDays(Carbon::parse($this->checkout_date));
                break;

            case 'App\Models\PrivateVanTour':
                $product      =  $this->product ? new PrivateVanTourResource($this->product) : $productSnapshot ?? null;
                $resolvedCar  = $variationSnapshot ?? ($this->car ?? null);
                break;

            case 'App\Models\EntranceTicket':
                $product           = $this->product ? new EntranceTicketResource($this->product) : $productSnapshot ?? null;
                $resolvedVariation = $variationSnapshot ?? ($this->variation ?? null);
                $resolvedTicket    = $variationSnapshot ?? ($this->ticket ?? null);
                break;

            case 'App\Models\Airline':
                $product        = $this->product ? new AirlineResource($this->product) : $productSnapshot ?? null;
                $resolvedTicket = $variationSnapshot ?? ($this->ticket ?? null);
                break;

            case 'App\Models\GroupTour':
                $product = $productSnapshot ?? ($this->product ? new GroupTourResource($this->product) : null);
                break;

            case 'App\Models\AirportPickup':
                $product = $productSnapshot ?? ($this->product ? new AirportPickupResource($this->product) : null);
                break;
        }

        // ---- Price comparison ----
        $priceComparison = $snapshotService->comparePriceWithCurrent($this->resource);

        return [
            'id' => $this->id,
            'crm_id' => $this->crm_id,
            'booking' => [
                ...$this->booking->toArray(),
                'receipts' => BookingReceiptResource::collection($this->booking->receipts),
                'payment_currency' => $this->booking->payment_currency,
                'payment_method' => $this->booking->payment_method,
                'payment_status' => $this->booking->payment_status,
                'bank_name' => $this->booking->bank_name,
                'output_vat' => $this->booking->output_vat,
                'commission' => $this->booking->commission,
            ],
            'customer_info' => $this->booking->customer,
            'customer_attachment' => $this->customer_attachment ? Storage::url('attachments/' . $this->customer_attachment) : null,
            'product_type' => $this->product_type,
            'product_id' => $this->product_id,
            'is_excluded' => $this->is_excluded,
            'product'   => $product,
            'car'       => $resolvedCar,
            'room'      => $resolvedRoom,
            'ticket'    => $resolvedTicket,
            'variation' => $resolvedVariation,
            // ✅ Snapshot data တွေ
            'product_snapshot'   => $this->product_snapshot,
            'variation_snapshot' => $this->variation_snapshot,
            'price_snapshot'     => $this->price_snapshot,
            'archive_snapshot'   => $this->archive_snapshot,

            // ✅ Price ပြောင်းသွားလား စစ်ထားသော result
            'price_comparison' => $priceComparison,

            'service_date' => $this->service_date ? Carbon::parse($this->service_date)->format('Y-m-d') : null,
            'formatted_service_date' => $this->service_date ? Carbon::parse($this->service_date)->format('d M Y') : null,
            'quantity' => $this->quantity,
            'total_guest' => $this->total_guest,
            'room_number' => $this->room_number,
            'duration' => $this->duration,
            'selling_price' => $this->selling_price,
            'cost_price' => $this->cost_price,
            'total_cost_price' => $this->total_cost_price,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'booking_status' => $this->booking_status,
            'bank_name' => $this->bank_name,
            'cost' => $this->cost,

            'amount' => $this->amount,
            'discount' => $this->discount,
            'is_inclusive' => $this->is_inclusive,

            'bank_account_number' => $this->bank_account_number,
            'exchange_rate' => $this->exchange_rate,
            'confirmation_letter' => $this->confirmation_letter ? Storage::url('files/' . $this->confirmation_letter) : null,
            'selling_price' => $this->selling_price,
            'comment' => $this->comment,
            'reservation_status' => $this->reservation_status,
            'expense_amount' => $this->expense_amount,

            'is_driver_collect' => $this->is_driver_collect,
            'contact_number' => $this->contact_number,
            'total_pax' => $this->total_pax,
            'collect_comment' => $this->collect_comment,
            'extra_collect_amount' => $this->extra_collect_amount,

            'route_plan' => $this->route_plan,
            'special_request' => $this->special_request,
            'dropoff_location' => $this->dropoff_location,
            'pickup_location' => $this->pickup_location,
            'pickup_time' => $this->pickup_time,

            'reservation_info' => $this->reservationInfo ? [
                "id" => $this->reservationInfo->id,
                "booking_item_id" => $this->reservationInfo->booking_item_id,
                "customer_feedback" => $this->reservationInfo->customer_feedback,
                "customer_score" => $this->reservationInfo->customer_score,
                "other_info" => $this->reservationInfo->other_info,
                "payment_method" => $this->reservationInfo->payment_method,
                "payment_status" => $this->reservationInfo->payment_status,
                "payment_due" => $this->reservationInfo->payment_due,
                "payment_receipt" => $this->reservationInfo->payment_receipt,
                "bank_name" => $this->reservationInfo->bank_name,
                "bank_account_number" => $this->reservationInfo->bank_account_number,
                "cost" => $this->reservationInfo->cost,
                "paid_slip" => $this->reservationInfo->paid_slip,
                "expense_amount" => $this->reservationInfo->expense_amount,
                "driver_score" => $this->reservationInfo->driver_score,
                "product_score" => $this->reservationInfo->product_score,
            ] : null,

            'checkin_date' => $this->checkin_date,
            'checkout_date' => $this->checkout_date,

            'formatted_checkin_date' => $this->checkin_date ? Carbon::parse($this->checkin_date)->format('d M Y') : null,
            'formatted_checkout_date' => $this->checkout_date ? Carbon::parse($this->checkout_date)->format('d M Y') : null,

            'stay_nights' => $stay_nights,
            'reservation_car_info' => new ReservationCarInfoResource($this->reservationCarInfo),
            'amend_info' => BookingItemAmendmentResource::collection($this->amendments),
            'reservation_supplier_info' => new ReservationCarInfoResource($this->reservationSupplierInfo),
            'booking_confirm_letters' => ReservationBookingConfirmLetterResource::collection($this->reservationBookingConfirmLetter),
            'receipt_images_real' => ReservationReceiptImageResource::collection($this->reservationReceiptImage),
            'customer_passports' => ReservationCustomerPassportResource::collection($this->reservationCustomerPassport),
            'paid_slip' => ReservationReceiptImageResource::collection($this->reservationPaidSlip),
            'tax_slip' => ReservationTaxSlipResource::collection($this->taxSlips),
            'associated_customer' => AssociatedCustomerResource::collection($this->associatedCustomer),
            'slip_code' => $this->slip_code,
            'is_associated' => $this->is_associated,
            // 'paid_slip' => $this->paid_slip ? Storage::url('images/' . $this->paid_slip) : null,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
            'individual_pricing' => $this->individual_pricing ? (is_string($this->individual_pricing) ? json_decode($this->individual_pricing) : $this->individual_pricing) : null,
            'child_price' => $this->child_price,
            'child_cost' => $this->child_cost,
            'child_total_cost' => $this->child_total_cost,
            'child_total_selling_price' => $this->child_total_selling_price,
            'child_quantity' => $this->child_quantity,
            'adult_price' => $this->adult_price,
            'adult_cost' => $this->adult_cost,
            'adult_total_cost' => $this->adult_total_cost,
            'adult_total_selling_price' => $this->adult_total_selling_price,
            'adult_quantity' => $this->adult_quantity,
            'infant_price' => $this->infant_price,
            'infant_cost' => $this->infant_cost,
            'infant_total_cost' => $this->infant_total_cost,
            'infant_total_selling_price' => $this->infant_total_selling_price,
            'infant_quantity' => $this->infant_quantity,

            'cancellation' => $this->cancellation,
            'addon' => $this->addon ? json_decode($this->addon) : null,
            'is_booking_request' => $this->is_booking_request,
            'is_expense_email_sent' => $this->is_expense_email_sent,
            'booking_requests' => ReservationBookingRequestResource::collection($this->requestProves),
            'expense_mail' => ReservationExpenseMailResource::collection($this->expenseMail),

            // cost case
            'cases' => isset($this->costCases) ? CaseDetailResource::collection($this->costCases) : '',

            'group_id' => $this->group_id,
            'is_allowment_have' => $this->is_allowment_have,
            'output_vat' => $this->output_vat,
            'commission' => $this->commission,
            'receipt_images' => $this->group?->cashImages && count($this->group->cashImages) ? CashImageResource::collection($this->group->cashImages) : [],
            // 'amend_info' => BookingItemAmendmentResource::collection($this->amendments),
            'has_amendment'  => $this->amendments->isNotEmpty(),
            'amend_info'     => $this->amendments->map(function ($amendment) {
                $snapshot = null;
                if ($amendment->item_snapshot) {
                    $snapshot = is_string($amendment->item_snapshot)
                        ? json_decode($amendment->item_snapshot, true)
                        : (array) $amendment->item_snapshot;
                }

                $latestHistory = collect($amendment->amend_history ?? [])->last();
                $latestChanges = $latestHistory['changes'] ?? [];

                return [
                    'id'              => $amendment->id,
                    'amend_status'    => $amendment->amend_status,
                    'amend_approve'   => $amendment->amend_approve,
                    'amend_request'   => $amendment->amend_request,
                    'amend_mail_sent' => $amendment->amend_mail_sent,
                    'is_delete'       => (bool) ($latestChanges['delete'] ?? false),
                    'latest_changes'  => $latestChanges,
                    'amend_history'   => $amendment->amend_history,
                    'has_snapshot'    => $snapshot !== null,
                    'created_at'      => $amendment->created_at,
                    'updated_at'      => $amendment->updated_at,
                ];
            }),
        ];
    }
}
