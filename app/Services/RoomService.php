<?php

namespace App\Services;

use App\Models\Room;
use Carbon\Carbon;

class RoomService
{
    public function __construct(public Room $room)
    {
        //
    }

    public function getPeriods()
    {
        return $this->room->periods()->get();
    }

    public function getRoomPrice($period = null)
    {
        if (is_null($period)) {
            return $this->room->room_price;
        }

        $dates = $this->getTotalDates($period);

        if (empty($dates)) {
            $date = explode(' , ', $period)[0];

            $periodMatch = $this->room->periods()
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->exists();

            if ($periodMatch) {
                return $this->room->periods()
                    ->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date)
                    ->sum('sale_price');
            } else {
                return $this->room->room_price;
            }
        }

        $room_prices = [];
        foreach ($dates as $date) {
            $query = $this->room->periods()
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date);

            if ($query->exists()) {
                $room_prices[] = $query->sum('sale_price');
            } else {
                $room_prices[] = $this->room->room_price;
            }
        }

        return array_sum($room_prices);
    }

    private function getTotalDates($period = null)
    {
        $dates = [];

        if (!is_null($period)) {
            $periods = explode(' , ', $period);

            $dates = $this->getDaysOfMonth($periods[0], $periods[1]);

            array_pop($dates);

            return $dates;
        }

        return $dates;
    }

    private function getDaysOfMonth($start_date, $end_date): array
    {
        $dates = Carbon::parse($start_date)
            ->daysUntil(Carbon::parse($end_date))
            ->map(fn ($date) => $date->format('Y-m-d'));

        return iterator_to_array($dates);
    }

    public function getDailyPricing($checkin_date, $checkout_date)
    {
        $dates = $this->getDatesBetween($checkin_date, $checkout_date);
        $discount = hotel_discount(); // Assuming this helper function exists

        $daily_pricing = [];
        $total_sale = 0;
        $total_cost = 0;
        $total_discount = 0;
        $total_selling_price = 0;

        foreach ($dates as $date) {
            $pricing = $this->getPriceForDate($date);

            // Calculate discount
            $room_price = (float)$pricing['sale_price'];
            $cost_price = (float)$pricing['cost_price'];
            $owner_price = (float)$this->room->owner_price;
            $room_name = $pricing['period_name'];

            $discount_price = ($room_price - $cost_price) * $discount;

            if ($owner_price != 0) {
                $discount_percent = ($owner_price - ($room_price - $discount_price)) / $owner_price * 100;
            } else {
                $discount_percent = 0;
            }

            $selling_price = $room_price - $discount_price;

            $daily_pricing[] = [
                'date' => $date,
                'period_name' => $room_name ?? '',
                'sale_price' => $room_price,
                'cost_price' => $cost_price,
                'discount_price' => $discount_price,
                'discount_percent' => round($discount_percent),
                'selling_price' => $selling_price
            ];

            $total_sale += $room_price;
            $total_cost += $cost_price;
            $total_discount += $discount_price;
            $total_selling_price += $selling_price;
        }

        return [
            'daily' => $daily_pricing,
            'total_sale' => $total_sale,
            'total_cost' => $total_cost,
            'total_discount' => $total_discount,
            'total_selling_price' => $total_selling_price,
            'overall_discount_percent' => $total_sale > 0 ? round(($total_discount / $total_sale) * 100) : 0
        ];
    }

    private function getPriceForDate($date)
    {
        // Check if date falls within any special period
        $period = $this->room->periods()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if ($period) {
            return [
                'sale_price' => $period->sale_price,
                'cost_price' => $period->cost_price ?? $this->room->cost_price,
                'period_name' => $period->period_name
            ];
        }

        // Return default room prices
        return [
            'sale_price' => $this->room->room_price,
            'cost_price' => $this->room->cost,
            'period_name' => 'default',
        ];
    }

    private function getDatesBetween($start_date, $end_date)
    {
        $dates = [];
        $current = Carbon::parse($start_date);
        $end = Carbon::parse($end_date);

        while ($current->lt($end)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }
}
