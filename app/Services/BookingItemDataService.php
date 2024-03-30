<?php
namespace App\Services;

use App\Models\BookingItem;
use Carbon\Carbon;

class BookingItemDataService
{
    public $booking_item;

    public function __construct(BookingItem $booking_item)
    {
        $this->booking_item = $booking_item;
    }

    public function getTotalCost()
    {
        return $this->getCostPrice() * $this->getQuantity();
    }

    public function getSalePrice()
    {
        return $this->booking_item->selling_price * $this->getQuantity();
    }

    public function getNights($checkin_date, $checkout_date)
    {
        return (int) Carbon::parse($checkin_date)->diff(Carbon::parse($checkout_date))->format("%a");
    }

    public function calcBalanceAmount(string $payment_method, $total_cost, $selling_price, $extra_collect_amount)
    {
        if($this->booking_item->is_driver_collect) {
            return ($selling_price + $extra_collect_amount) - $total_cost;
        }  {
            return $total_cost * (-1);
        }
    }

    /**
     * Static Methods
     */
    public static function getCarBookingSummary(array $filters)
    {
        $booking_items = BookingItem::privateVanTour()
            ->when($filters['supplier_id'] ?? null, function ($query) use ($filters) {
                $query->whereHas('reservationCarInfo', fn ($query) => $query->where('supplier_id', $filters['supplier_id']));
            })
            ->when($filters['daterange'] ?? null, function ($query) use ($filters) {
                $dates = explode(',', $filters['daterange']);

                $query->whereBetween('service_date', [$dates[0], $dates[1]]);
            })
            ->when($filters['agent_id'] ?? null, function ($query) use ($filters) {
                $query->whereHas('booking', fn ($q) => $q->where('created_by', $filters['agent_id']));
            })
            ->get();

        $total_balance = 0;
        foreach($booking_items as $booking_item) {
            $self = new static($booking_item);

            $total_balance += $self->calcBalanceAmount(
                $booking_item->booking->payment_method,
                $self->getTotalCost(),
                $booking_item->selling_price,
                $booking_item->extra_collect_amount
            );
        }

        return [
            'total_booking' => $booking_items->groupBy('booking_id')->count(),
            'total_sales' => $booking_items->count(),
            'total_cost' => $booking_items->sum('total_cost_price'),
            'total_balance' => $total_balance
        ];
    }

    private function getCostPrice(): int
    {
        $cost_price = null;

        $booking_item = $this->booking_item;

        if($booking_item->cost_price == null || $booking_item->cost_price == 0) {
            if($booking_item->room) {
                $cost_price = $booking_item->room->cost ?? 0;
            }

            if($booking_item->variation) {
                $cost_price = $booking_item->variation->cost_price ?? 0;
            }

            if($booking_item->car || $booking_item->product_type == "App\Models\GroupTour" || $booking_item->product_type == "App\Models\Airline") {
                $cost_price = 0;
            }
        } else {
            $cost_price = $booking_item->cost_price;
        }

        return (int) $cost_price;
    }

    private function getQuantity(): int
    {
        if($this->booking_item->product_type == 'App\Models\Hotel') {
            return $this->booking_item->quantity * $this->getNights($this->booking_item->checkin_date, $this->booking_item->checkout_date);
        }

        return (int) $this->booking_item->quantity;
    }
}
