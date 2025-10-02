<?php
namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\BookingItem;
use App\Models\Hotel;
use App\Models\PartnerRoomRate;
use App\Models\Room;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class RoomRateController extends Controller
{
    use HttpResponses;

    public function store(Hotel $hotel, Room $room, Request $request)
    {
        $partner = $request->user();

        $validated = $request->validate([
            'dates' => 'required|array|min:1',
            'dates.*' => 'required|date|date_format:Y-m-d',
            'stock' => 'required|integer|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        if ($request->type && $request->type == 'all') {
            $validated['dates'] = [];
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();
            for ($date = $startOfMonth; $date->lte($endOfMonth); $date->addDay()) {
                $validated['dates'][] = $date->format('Y-m-d');
            }
        }

        $results = [];
        foreach ($validated['dates'] as $date) {
            $booked = BookingItem::query()
                ->where('room_id', $room->id)
                ->where('service_date', $date)
                ->sum('quantity');

            if ($booked > $validated['stock']) {
                $results[] = [
                    'date' => $date,
                    'error' => 'Cannot set stock below already booked quantity (' . $booked . ') for this room and date.'
                ];

                continue;
            }

            $rate = PartnerRoomRate::updateOrCreate(
                [
                    'partner_id' => $partner->id,
                    'room_id' => $room->id,
                    'date' => $date,
                ],
                [
                    'stock' => $validated['stock'],
                    'discount' => $validated['discount'] ?? 0,
                ]
            );
            $results[] = [
                'date' => $date,
                'success' => true,
                'rate' => $rate,
            ];
        }

        $room->refresh();
        $request->merge(['year' => now()->year, 'month' => now()->month, 'include_rates' => true]);

        return $this->success(new RoomResource($room), 'Room Detail', 200);
    }

    public function destroy(Hotel $hotel, Room $room, Request $request)
    {
        $partner = $request->user();

        $validated = $request->validate([
            'date' => 'required|date|date_format:Y-m-d',
        ]);

        $rate = PartnerRoomRate::where('partner_id', $partner->id)
            ->where('room_id', $room->id)
            ->where('date', $validated['date'])
            ->firstOrFail();

        $rate->delete();

        return $this->success(null, 'Room rate deleted successfully', 200);
    }
}
