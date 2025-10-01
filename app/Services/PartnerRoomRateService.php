<?php
namespace App\Services;

use App\Models\PartnerRoomRate;

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
}
