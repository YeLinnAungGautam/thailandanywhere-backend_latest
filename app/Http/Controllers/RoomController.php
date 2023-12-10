<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\RoomImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    use ImageManager;
    use HttpResponses;    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $order_by_price = $request->query('order_by_price');

        $query = Room::query();

        if($order_by_price) {
            if($order_by_price == 'low_to_high') {
                $query->orderBy('room_price');
            } elseif($order_by_price == 'high_to_low') {
                $query->orderByDesc('room_price');
            }
        }

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($request->hotel_id) {
            $query->where('hotel_id', $request->hotel_id);
        }

        $data = $query->paginate($limit);

        return $this->success(RoomResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Room List');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoomRequest $request)
    {
        $save = Room::create([
            'hotel_id' => $request->hotel_id,
            'name' => $request->name,
            'cost' => $request->cost,
            'extra_price' => $request->extra_price,
            'room_price' => $request->room_price,
            'description' => $request->description,
            'max_person' => $request->max_person,
            'is_extra' => $request->is_extra ?? 0
        ]);

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                $fileData = $this->uploads($image, 'images/');
                RoomImage::create(['room_id' => $save->id, 'image' => $fileData['fileName']]);
            };
        }

        return $this->success(new RoomResource($save), 'Successfully created', 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Room $room)
    {
        return $this->success(new RoomResource($room), 'Room Detail', 200);

    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoomRequest $request, Room $room)
    {

        $room->update([
            'name' => $request->name ?? $room->name,
            'hotel_id' => $request->hotel_id ?? $room->hotel_id,
            'cost' => $request->cost ?? $room->cost,
            'description' => $request->description ?? $room->description,
            'extra_price' => $request->extra_price ?? $room->extra_price,
            'room_price' => $request->room_price ?? $room->room_price,
            'max_person' => $request->max_person,
            'is_extra' => $request->is_extra ?? 0
        ]);

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                $fileData = $this->uploads($image, 'images/');
                RoomImage::create(['room_id' => $room->id, 'image' => $fileData['fileName']]);
            };
        }

        return $this->success(new RoomResource($room), 'Successfully updated', 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Room $room)
    {
        $room_images = RoomImage::where('room_id', '=', $room->id)->get();

        foreach($room_images as $room_image) {

            Storage::delete('public/images/' . $room_image->image);

        }

        RoomImage::where('room_id', $room->id)->delete();

        $room->delete();

        return $this->success(null, 'Successfully deleted', 200);
    }

    public function deleteImage(Room $room, RoomImage $room_image)
    {
        if ($room->id !== $room_image->room_id) {
            return $this->error(null, 'This image is not belongs to the room', 404);
        }

        Storage::delete('public/images/' . $room_image->image);

        $room_image->delete();

        return $this->success(null, 'Room image is successfully deleted');
    }
}
