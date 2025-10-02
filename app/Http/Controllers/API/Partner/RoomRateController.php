<?php
namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerRoomRateResource;
use App\Models\BookingItem;
use App\Models\Hotel;
use App\Models\PartnerRoomRate;
use App\Models\Room;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class RoomRateController extends Controller
{
    use HttpResponses;

    public function index(Hotel $hotel, Room $room, Request $request)
    {
        $request->validate([
            'month' => 'required|digits:2',
            'year' => 'required|digits:4',
        ]);

        $room_rates = PartnerRoomRate::query()
            ->with('room')
            ->where('partner_id', $request->user()->id)
            ->where('room_id', $room->id)
            ->whereYear('date', $request->input('year'))
            ->whereMonth('date', $request->input('month'))
            ->when($request->input('date'), fn ($q, $date) => $q->where('date', $date))
            ->withSum([
                'bookingItems as booked_count' => function ($query) {
                    $query->whereColumn('service_date', 'date');
                }
            ], 'quantity')
            ->paginate($request->limit ?? 31);

        return $this->success(PartnerRoomRateResource::collection($room_rates)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($room_rates->total() / $room_rates->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Room Rate List');
    }

    public function show($id)
    {
        $rate = PartnerRoomRate::with(['room', 'partner'])->findOrFail($id);

        return $this->success(
            $rate,
            'Room rate retrieved'
        );
    }

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
                    'cost_price' => $validated['cost_price'] ?? null,
                    'cost_price_discount' => $validated['cost_price_discount'] ?? null,
                    'selling_price' => $validated['selling_price'] ?? null,
                    'discount' => $validated['discount'] ?? 0,
                ]
            );
            $results[] = [
                'date' => $date,
                'success' => true,
                'rate' => $rate,
            ];
        }

        return $this->success(
            $results,
            'Batch room rates processed',
            200
        );
    }

    public function update(Request $request, $id)
    {
        $rate = PartnerRoomRate::findOrFail($id);
        $validated = $request->validate([
            'stock' => 'sometimes|integer|min:0',
            'price' => 'sometimes|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        // If stock is being updated, check for booking conflicts
        if (isset($validated['stock'])) {
            $booked = BookingItem::where('room_id', $rate->room_id)
                ->where('service_date', $rate->date)
                ->sum('quantity');

            if ($booked > $validated['stock']) {
                return response()->json([
                    'error' => 'Cannot set stock below already booked quantity (' . $booked . ') for this room and date.'
                ], 422);
            }
        }

        $rate->update($validated);

        return response()->json(['data' => $rate, 'message' => 'Room rate updated']);
    }

    public function destroy($id)
    {
        $rate = PartnerRoomRate::findOrFail($id);
        $rate->delete();

        return response()->json(['message' => 'Room rate deleted']);
    }
}
