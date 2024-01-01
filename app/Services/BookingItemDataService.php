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

    private function getCostPrice()
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

        return $cost_price;
    }

    private function getQuantity()
    {
        if($this->booking_item->product_type == 'App\Models\Hotel') {
            return $this->booking_item->quantity * $this->getNights($this->booking_item->checkin_date, $this->booking_item->checkout_date);
        }

        return $this->booking_item->quantity;
    }

    private function getNights($checkin_date, $checkout_date)
    {
        return (int) Carbon::parse($checkin_date)->diff(Carbon::parse($checkout_date))->format("%a");
    }
}
