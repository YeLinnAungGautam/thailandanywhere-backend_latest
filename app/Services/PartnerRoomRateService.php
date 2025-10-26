<?php
namespace App\Services;

use App\Models\BookingItem;
use App\Models\PartnerRoomMeta;
use App\Models\PartnerRoomRate;
use App\Models\Room;
use Carbon\Carbon;

class PartnerRoomRateService
{
    public function __construct(public string $partner_id, public int $room_id)
    {

    }

    public function getRates(int $year, int $month, ?string $date = null)
    {
        $room_rates = PartnerRoomRate::query()
            ->with('room')
            ->where('partner_id', $this->partner_id)
            ->where('room_id', $this->room_id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->when($date, fn ($q, $date) => $q->where('date', $date))
            ->withSum([
                'bookingItems as booked_count' => function ($query) {
                    $query->whereColumn('service_date', 'date');
                }
            ], 'quantity')
            ->get();

        return $room_rates;
    }

    public function getRateForDate(string $date)
    {
        // First, check if PartnerRoomRate exists for this date
        $roomRate = PartnerRoomRate::where('partner_id', $this->partner_id)
            ->where('room_id', $this->room_id)
            ->where('date', $date)
            ->withSum([
                'bookingItems as booked_count' => function ($query) use ($date) {
                    $query->where('service_date', $date);
                }
            ], 'quantity')
            ->first();

        if ($roomRate) {
            return $roomRate;
        }

        // If no PartnerRoomRate exists, fallback to PartnerRoomMeta defaults
        $roomMeta = PartnerRoomMeta::where('partner_id', $this->partner_id)
            ->where('room_id', $this->room_id)
            ->first();

        $room = Room::find($this->room_id);

        // Create a virtual PartnerRoomRate object with meta defaults
        $virtualRate = new PartnerRoomRate([
            'partner_id' => $this->partner_id,
            'room_id' => $this->room_id,
            'date' => $date,
            'stock' => $roomMeta->stock ?? 0,
            'discount' => $roomMeta->discount ?? 0,
        ]);

        // Add room relationship and booked count
        $virtualRate->setRelation('room', $room);
        $virtualRate->booked_count = BookingItem::query()
            ->where('room_id', $this->room_id)
            ->where('service_date', $date)
            ->sum('quantity');

        return $virtualRate;
    }

    public function getRatesWithFallback(int $year, int $month)
    {
        // Get all days in the month
        $startDate = Carbon::createFromDate($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $allDates = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $allDates[] = $date->format('Y-m-d');
        }

        // Get existing PartnerRoomRates for this month
        $existingRates = PartnerRoomRate::query()
            ->where('partner_id', $this->partner_id)
            ->where('room_id', $this->room_id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->withSum([
                'bookingItems as booked_count' => function ($query) {
                    $query->whereColumn('service_date', 'date');
                }
            ], 'quantity')
            ->get()
            ->keyBy('date');

        // Get room meta for fallback
        $roomMeta = PartnerRoomMeta::query()
            ->where('partner_id', $this->partner_id)
            ->where('room_id', $this->room_id)
            ->first();

        $room = Room::find($this->room_id);
        $rates = collect();

        foreach ($allDates as $date) {
            if (isset($existingRates[$date])) {
                // Use existing PartnerRoomRate
                $rates->push($existingRates[$date]);
            } else {
                // Create virtual rate with meta defaults
                $virtualRate = new PartnerRoomRate([
                    'partner_id' => $this->partner_id,
                    'room_id' => $this->room_id,
                    'date' => $date,
                    'stock' => $roomMeta->stock ?? 0,
                    'discount' => $roomMeta->discount ?? 0,
                ]);

                $virtualRate->setRelation('room', $room);
                $virtualRate->booked_count = BookingItem::query()
                    ->where('room_id', $this->room_id)
                    ->where('service_date', $date)
                    ->sum('quantity');

                $rates->push($virtualRate);
            }
        }

        return $rates;
    }

    public function getCalendarDetail(int $year, int $month): array
    {
        $room_rates = $this->getRatesWithFallback(
            year: (int) $year,
            month: (int) $month
        );

        $calendar_detail = [];
        foreach ($room_rates as $rate) {
            $available_rooms = $rate['stock'] - ($rate['booked_count'] ?? 0);
            $room_price = $rate['room'] && $rate['room']->room_price ? $rate['room']->room_price : 0;
            $current_price = $room_price - $rate['discount'];

            $calendar_detail[$rate['date']] = [
                'display_date' => Carbon::parse($rate['date'])->format('D, M j Y'),
                'stock' => $rate['stock'],
                'booked_count' => $rate['booked_count'] ?? 0,
                'available_rooms' => $available_rooms,
                'room_price' => (float) $room_price,
                'current_price' => (float) $current_price,
                'discount' => (float) $rate['discount'],
            ];
        }

        return $calendar_detail;
    }

    public function formatCalendarDetails(array $room_rates)
    {
        $calendar_detail = [];
        foreach ($room_rates as $rate) {
            $available_rooms = $rate['stock'] - ($rate['booked_count'] ?? 0);
            $room_price = $rate['room'] && $rate['room']->room_price ? $rate['room']->room_price : 0;
            $current_price = $room_price - $rate['discount'];

            $calendar_detail[$rate['date']] = [
                'display_date' => Carbon::parse($rate['date'])->format('D, M j Y'),
                'stock' => $rate['stock'],
                'booked_count' => $rate['booked_count'] ?? 0,
                'available_rooms' => $available_rooms <= 0 ? 0 : $available_rooms,
                'room_price' => (float) $room_price,
                'current_price' => (float) $current_price,
                'discount' => (float) $rate['discount'],

                'display_str' => Carbon::parse($rate['date'])->format('M j') .
                    ' - Price: ' . number_format($current_price, 2) .
                    ' (' . ($available_rooms <= 0 ? 'Sold Out' : 'Stock: ' . $available_rooms) . ')',
            ];
        }

        return $calendar_detail;
    }

    public function getRateForDaterange($checkin_date, $checkout_date)
    {
        $dates = [];
        $currentDate = Carbon::parse($checkin_date);
        $endDate = Carbon::parse($checkout_date);

        while ($currentDate->lt($endDate)) {
            $dates[] = $currentDate->toDateString();
            $currentDate->addDay();
        }

        $rates = [];
        foreach ($dates as $date) {
            $rate = $this->getRateForDate($date);
            $rates[$date] = $rate;
        }

        return $this->formatCalendarDetails($rates);
    }

    public function isIncompleteAllotment($checkin_date, $checkout_date)
    {
        $currentDate = Carbon::parse($checkin_date);
        $endDate = Carbon::parse($checkout_date);

        while ($currentDate->lt($endDate)) {
            $rate = $this->getRateForDate($currentDate->toDateString());
            $available_rooms = $rate->stock - ($rate->booked_count ?? 0);
            if ($available_rooms <= 0) {
                return true;
            }
            $currentDate->addDay();
        }

        return false;
    }
}
