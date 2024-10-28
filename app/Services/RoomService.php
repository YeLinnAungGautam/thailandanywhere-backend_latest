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

    public function getRoomPriceBy($service_date)
    {
        $room_prices = [];
        $query = $this->room->periods()
            ->where('start_date', '<=', $service_date)
            ->where('end_date', '>=', $service_date);

        if ($query->exists()) {
            $room_prices[] = $query->sum('sale_price');
        } else {
            $room_prices[] = $this->room->room_price;
        }

        return array_sum($room_prices);
    }

    public function getRoomPrice($period = null)
    {
        if (is_null($period)) {
            return $this->room->room_price;
        }

        $dates = $this->getTotalDates($period);

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
}
