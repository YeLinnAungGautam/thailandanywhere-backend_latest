<?php

namespace App\Http\Resources;

use App\Models\BookingItem;
use App\Models\EntranceTicket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $total_cost = $this->getCostPrice() * $this->getQuantity();
        $sale_price = $this->amount;
        $unpaidCount = $this->getEntranceTicketStats()['unpaid_count'];
        $totalCount = $this->getEntranceTicketStats()['reservations_count'];

        $data = [
            'total_cost' => $total_cost,
            'bank_name' => $this->product->bank_name,
            'bank_account_number' => $this->product->bank_account_number,
            'account_name' => $this->product->account_name ?? '-',
            'crm_id' => $this->booking->crm_id,
            'reservation_code' => $this->crm_id,
            'sale_price' => $sale_price,
            'sale_date' => $this->booking->booking_date,
            'service_date' => $this->service_date,
            'checkin_date' => $this->checkin_date,
            'checkout_date' => $this->checkout_date,
            'score' => number_format(($sale_price - $total_cost) / $sale_price, 4),
            'payment_method' => $this->booking->payment_method,
            'payment_status' => $this->booking->payment_status,
            'product_name' => $this->product->name ?? '-',
            'expense_comment' => '',
            'expense_status' => $this->payment_status,
            'balance_due' => $this->booking->balance_due,
            'total_sale_amount' => $this->booking->sub_total,
            // 'individual_pricing' => is_null($this->individual_pricing) ? null : json_decode($this->individual_pricing),
            'individual_pricing' => $this->individual_pricing ? (is_string($this->individual_pricing) ? json_decode($this->individual_pricing) : $this->individual_pricing) : null,
            'discount' => $this->discount ?? 0,
            'selling_price' => $this->selling_price,
            'quantity' => $this->quantity,
            // 'individual_pricing' => $this->individual_pricing ? json_decode($this->individual_pricing) : [],
            'total_cost_price' => $this->total_cost_price,

            'reservation_group_id' => $this->group->id ?? null,
        ];

        if ($this->product_type == 'App\Models\Hotel') {
            $data['checkin_date'] = $this->checkin_date ? Carbon::parse($this->checkin_date)->format('d M Y') : null;
            $data['checkout_date'] = $this->checkout_date ? Carbon::parse($this->checkout_date)->format('d M Y') : null;
            $data['room_name'] = $this->room->name;
            // $data['hotel_name'] = $this->product->name;
            $data['total_rooms'] = $this->quantity;
            $data['total_nights'] = $this->getNights($this->checkin_date, $this->checkout_date);
        }

        if ($this->product_type == 'App\Models\Airline') {
            // $data['airline_name'] = $this->product->name;
            $data['ticket_type'] = $this->ticket->price;
            $data['total_ticket'] = $this->getQuantity();
        }

        if ($this->product_type === EntranceTicket::class) {
            $data['entrance_ticket_variation_name'] = $this->variation->name ?? '-';
            $data['unpaid_count'] = $unpaidCount;
            $data['total_count'] = $totalCount;
        }

        return $data;
    }

    private function getEntranceTicketStats()
    {
        $today = Carbon::today();
        $endDate = Carbon::today()->addDays(90);

        // Count of unpaid entrance tickets for today and next 90 days
        $unpaidCount = BookingItem::where('product_type', EntranceTicket::class)
            ->where('payment_status', '!=', 'paid')
            ->whereDate('service_date', '>=', $today)
            ->whereDate('service_date', '<=', $endDate)
            ->count();

        // Count of all entrance ticket reservations for today and next 90 days
        $reservationsCount = BookingItem::where('product_type', EntranceTicket::class)
            ->whereDate('service_date', '>=', $today)
            ->whereDate('service_date', '<=', $endDate)
            ->count();

        return [
            'unpaid_count' => $unpaidCount,
            'reservations_count' => $reservationsCount
        ];
    }

    private function getCostPrice()
    {
        $cost_price = null;

        if ($this->cost_price == null || $this->cost_price == 0) {
            if ($this->room) {
                $cost_price = $this->room->cost ?? 0;
            }

            if ($this->variation) {
                $cost_price = $this->variation->cost_price ?? 0;
            }

            if ($this->car || $this->product_type == "App\Models\GroupTour" || $this->product_type == "App\Models\Airline") {
                $cost_price = 0;
            }
        } else {
            $cost_price = $this->cost_price;
        }

        return $cost_price;
    }

    private function getNights($checkin_date, $checkout_date)
    {
        return (int) Carbon::parse($checkin_date)->diff(Carbon::parse($checkout_date))->format("%a");
    }

    private function getQuantity()
    {
        if ($this->product_type == 'App\Models\Hotel') {
            return $this->quantity * $this->getNights($this->checkin_date, $this->checkout_date);
        }

        return $this->quantity;
    }
}
