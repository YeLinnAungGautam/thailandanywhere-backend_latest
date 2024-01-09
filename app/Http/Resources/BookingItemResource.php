<?php

namespace App\Http\Resources;

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
        $product = null;
        switch ($this->product_type) {
            case 'App\Models\PrivateVanTour':
                $product = new PrivateVanTourResource($this->product);

                break;
            case 'App\Models\GroupTour':
                $product = new GroupTourResource($this->product);

                break;
            case 'App\Models\EntranceTicket':
                $product = new EntranceTicketResource($this->product);

                break;
            case 'App\Models\AirportPickup':
                $product = new AirportPickupResource($this->product);

                break;
            case 'App\Models\Hotel':
                $product = new HotelResource($this->product);

                break;
            case 'App\Models\Airline':
                $product = new AirlineResource($this->product);

                break;
            default:
                $product = null;

                break;
        }

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
            ],
            'customer_info' => $this->booking->customer,
            'customer_attachment' => $this->customer_attachment ? config('app.url') . Storage::url('attachments/' . $this->customer_attachment) : null,
            'product_type' => $this->product_type,
            'product_id' => $this->product_id,
            'product' => $product,
            'car' => $this->car,
            'room' => $this->room,
            'variation' => $this->variation,
            'service_date' => $this->service_date,
            'quantity' => $this->quantity,
            'total_guest' => $this->total_guest,
            'room_number' => $this->room_number,
            'duration' => $this->duration,
            'selling_price' => $this->selling_price,
            'cost_price' => $this->cost_price,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'bank_name' => $this->bank_name,
            'cost' => $this->cost,
            'bank_account_number' => $this->bank_account_number,
            'exchange_rate' => $this->exchange_rate,
            'confirmation_letter' => $this->confirmation_letter ? config('app.url') . Storage::url('files/' . $this->confirmation_letter) : null,
            'selling_price' => $this->selling_price,
            'comment' => $this->comment,
            'reservation_status' => $this->reservation_status,
            'special_request' => $this->special_request,
            'route_plan' => $this->route_plan,
            'expense_amount' => $this->expense_amount,
            'pickup_location' => $this->pickup_location,
            'pickup_time' => $this->pickup_time,
            'dropoff_location' => $this->dropoff_location,
            'checkin_date' => $this->checkin_date,
            'checkout_date' => $this->checkout_date,
            'reservation_info' => $this->reservationInfo,
            'reservation_car_info' => new ReservationCarInfoResource($this->reservationCarInfo),
            'reservation_supplier_info' => new ReservationCarInfoResource($this->reservationSupplierInfo),
            'booking_confirm_letters' => ReservationBookingConfirmLetterResource::collection($this->reservationBookingConfirmLetter),
            'receipt_images' => ReservationReceiptImageResource::collection($this->reservationReceiptImage),
            'customer_passports' => ReservationCustomerPassportResource::collection($this->reservationCustomerPassport),
            'paid_slip' => ReservationReceiptImageResource::collection($this->reservationPaidSlip),
            'associated_customer' => AssociatedCustomerResource::collection($this->associatedCustomer),
            'slip_code' => $this->slip_code,
            'is_associated' => $this->is_associated,
            // 'paid_slip' => $this->paid_slip ? config('app.url') . Storage::url('images/' . $this->paid_slip) : null,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
