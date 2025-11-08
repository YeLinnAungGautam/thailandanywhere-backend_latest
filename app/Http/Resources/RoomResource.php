<?php

namespace App\Http\Resources;

use App\Services\PartnerRoomRateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hotel' => new HotelResource($this->hotel),
            'extra_price' => $this->extra_price,
            'room_price' => $this->getRoomPrice($request->period),
            'is_extra' => $this->is_extra,
            'has_breakfast' => $this->has_breakfast,
            'cost' => $this->cost,
            'agent_price' => $this->agent_price ?? 0,
            'owner_price' => $this->owner_price,
            'description' => $this->description,
            'images' => RoomImageResource::collection($this->images),
            'room_periods' => RoomPeriodResource::collection($this->periods),
            'max_person' => $this->max_person,
            'partner_id' => $this->roomRates,
            'total_night' => count($this->getTotalDates($request->period)) <= 0 ? 1 : count($this->getTotalDates($request->period)),
            'deleted_at' => $this->deleted_at,
            'updated_at' => $this->updated_at,
            'updated_at' => $this->updated_at,
            'amenities' => $this->amenities ? json_decode($this->amenities) : $this->amenities,
            'meta' => $this->meta ? json_decode($this->meta) : $this->meta,
            'roitems' => RoitemResource::collection($this->whenLoaded('roitems')),
            'roitems_grouped' => $this->when(
                $this->relationLoaded('roitems'),
                function () {
                    return $this->roitemsGrouped();
                }
            ),
            'score' => $this->calculateScore(),
            'room_rates' => $this->when(
                $request->user() && $request->include_rates,
                function () use ($request) {
                    return $this->getRoomRates($request);
                }
            ),
        ];
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

    private function getRoomPrice($period = null)
    {
        if (is_null($period)) {
            return $this->room_price;
        }

        // $periods = $period ? explode(' , ', $period) : null;
        // $dates = $this->getDaysOfMonth($periods[0], $periods[1]);

        $dates = $this->getTotalDates($period);

        $room_prices = [];
        foreach ($dates as $date) {
            $query = $this->periods()
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date);

            if ($query->exists()) {
                $room_prices[] = $query->sum('sale_price');
            } else {
                $room_prices[] = $this->room_price;
            }
        }

        return array_sum($room_prices);
    }

    private function getDaysOfMonth($start_date, $end_date): array
    {
        $dates = Carbon::parse($start_date)
            ->daysUntil(Carbon::parse($end_date))
            ->map(fn ($date) => $date->format('Y-m-d'));

        return iterator_to_array($dates);
    }

    private function calculateScore()
    {
        if ($this->room_price > 0) {
            return round(($this->room_price - $this->cost) / $this->room_price, 3);
        }

        return 0;
    }

    private function getRoomRates($request)
    {
        $year = $request->year ?? now()->year;
        $month = $request->month ?? now()->month;

        // Get partner_id from request, authenticated user, or from PartnerRoomMeta
        $partner_id = $this->partnerRoomMetas()->first()?->partner_id;

        if (!$partner_id) {
            return null;
        }

        $service = new PartnerRoomRateService(
            partner_id: $partner_id,
            room_id: $this->id
        );

        $room_rates = $service->getCalendarDetail(
            year: (int) $year,
            month: (int) $month
        );

        // return $partner_id;
        return $room_rates;
    }
}
