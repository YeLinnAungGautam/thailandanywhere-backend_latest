<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\RoomImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    use ImageManager;
    use HttpResponses;

    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $order_by_price = $request->query('order_by_price');

        $query = Room::query()->with('periods', 'images', 'hotel');

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

        if($request->periods) {
            foreach ($request->periods as $period) {
                $save->periods()->create($period);
            }
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
        DB::beginTransaction();

        try {
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

            if($request->periods) {
                $dates = collect($request->periods)->map(function ($period) {
                    return collect($period)->only(['start_date', 'end_date'])->all();
                });

                $overlap_dates = $this->checkIfOverlapped($dates);

                $room_periods = [];
                foreach($request->periods as $period) {
                    $sd_exists = in_array($period['start_date'], array_column($overlap_dates, 'start_date'));
                    $ed_exists = in_array($period['end_date'], array_column($overlap_dates, 'end_date'));

                    if(!$sd_exists && !$ed_exists) {
                        $room_periods[] = $period;
                    }
                }

                $this->syncPeriods($room, $room_periods);
            }

            DB::commit();

            return $this->success(new RoomResource($room), 'Successfully updated', 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return $this->error(null, $e->getMessage(), 401);
        }
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

    private function syncPeriods(Room $room, array $periods)
    {
        $array_of_ids = [];

        foreach ($periods as $period) {
            $job = $room->periods()->updateOrCreate([
                'period_name' => $period['period_name'],
                'start_date' => $period['start_date'],
                'end_date' => $period['end_date'],
                'sale_price' => $period['sale_price'],
                'cost_price' => $period['cost_price'],
            ]);

            $array_of_ids[] = $job->id;
        }

        $room->periods->whereNotIn('id', $array_of_ids)->each->delete();
    }

    private function checkIfOverlapped($ranges)
    {
        $overlaps = [];
        for($i = 0; $i < count($ranges); $i++) {
            for($j = ($i + 1); $j < count($ranges); $j++) {

                $start = \Carbon\Carbon::parse($ranges[$j]['start_date']);
                $end = \Carbon\Carbon::parse($ranges[$j]['end_date']);

                $start_first = \Carbon\Carbon::parse($ranges[$i]['start_date']);
                $end_first = \Carbon\Carbon::parse($ranges[$i]['end_date']);

                if(\Carbon\Carbon::parse($ranges[$i]['start_date'])->between($start, $end) || \Carbon\Carbon::parse($ranges[$i]['end_date'])->between($start, $end)) {
                    $overlaps[] = $ranges[$j];

                    break;
                }
                if(\Carbon\Carbon::parse($ranges[$j]['start_date'])->between($start_first, $end_first) || \Carbon\Carbon::parse($ranges[$j]['end_date'])->between($start_first, $end_first)) {
                    $overlaps[] = $ranges[$j];

                    break;
                }
            }
        }

        return $overlaps;
    }
}
