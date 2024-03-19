<?php
namespace App\Services;

use App\Models\BookingItem;
use App\Models\PrivateVanTour;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

    /**
     * Static Methods
     */
    public static function getTotalSummary(string $product_type)
    {
        if(PrivateVanTour::class === $product_type) {
            $total_booking = BookingItem::privateVanTour()
                ->groupBy('booking_id')
                ->select(DB::raw('count(*) as total_count'))
                ->get()
                ->count();

            return [
                'total_booking' => $total_booking,
                'total_sales' => BookingItem::privateVanTour()->count(),
                'total_cost' => 0000,
                'total_balance' => 0000
            ];
        }

        return [];
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
}
