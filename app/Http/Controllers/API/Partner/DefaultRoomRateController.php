<?php
namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Hotel;
use App\Models\PartnerRoomMeta;
use App\Models\Room;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class DefaultRoomRateController extends Controller
{
    use HttpResponses;

    public function store(Hotel $hotel, Room $room, Request $request)
    {
        $partner = $request->user();

        $validated = $request->validate([
            'stock' => 'required|integer|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        $meta = PartnerRoomMeta::updateOrCreate(
            [
                'partner_id' => $partner->id,
                'room_id' => $room->id,
            ],
            [
                'stock' => $validated['stock'],
                'discount' => $validated['discount'] ?? 0,
            ]
        );

        $room->refresh();
        $request->merge(['year' => now()->year, 'month' => now()->month, 'include_rates' => true]);

        return $this->success(new RoomResource($room), 'Room Detail', 200);
    }
}
