<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\RoomImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $order_by_score = $request->query('order_by_score');

        $query = Room::query()->with('periods', 'images', 'hotel','roitems', 'roitems.rofacility');

        // Add score calculation using raw SQL
        $query->selectRaw('rooms.*,
            CASE
                WHEN room_price > 0 THEN (room_price - cost) / room_price
                ELSE 0
            END as score');

        // Handle sorting
        if ($order_by_score) {
            if ($order_by_score == 'low_to_high') {
                $query->orderByRaw('
                    CASE
                        WHEN room_price > 0 THEN (room_price - cost) / room_price
                        ELSE 0
                    END ASC'
                );
            } elseif ($order_by_score == 'high_to_low') {
                $query->orderByRaw('
                    CASE
                        WHEN room_price > 0 THEN (room_price - cost) / room_price
                        ELSE 0
                    END DESC'
                );
            }
        } elseif ($order_by_price) {
            if ($order_by_price == 'low_to_high') {
                $query->orderBy('room_price');
            } elseif ($order_by_price == 'high_to_low') {
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
        DB::beginTransaction();

        try {
            $save = Room::create([
                'hotel_id' => $request->hotel_id,
                'name' => $request->name,
                'cost' => $request->cost,
                'extra_price' => $request->extra_price,
                'has_breakfast' => $request->has_breakfast,
                'room_price' => $request->room_price,
                'description' => $request->description,
                'max_person' => $request->max_person,
                'is_extra' => $request->is_extra ?? 0,
                'agent_price' => $request->agent_price,
                'owner_price' => $request->owner_price,
                'amenities' => $request->amenities ? json_encode($request->amenities) : null,
                'meta' => $request->meta ? json_encode($request->meta) : null,
            ]);

            // Handle images
            if ($request->file('images')) {
                foreach ($request->file('images') as $image) {
                    $fileData = $this->uploads($image, 'images/');
                    RoomImage::create(['room_id' => $save->id, 'image' => $fileData['fileName']]);
                }
            }

            // ✅ Handle periods - Create all new periods
            if ($request->periods && is_array($request->periods)) {
                foreach ($request->periods as $period) {
                    $save->periods()->create([
                        'period_name' => $period['period_name'],
                        'start_date' => $period['start_date'],
                        'end_date' => $period['end_date'],
                        'sale_price' => $period['sale_price'],
                        'cost_price' => $period['cost_price'] ?? null,
                        'agent_price' => $period['agent_price'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return $this->success(new RoomResource($save), 'Successfully created', 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
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
                'is_extra' => $request->is_extra ?? 0,
                'has_breakfast' => $request->has_breakfast ?? $room->has_breakfast,
                'agent_price' => $request->agent_price ?? $room->agent_price,
                'owner_price' => $request->owner_price ?? $room->owner_price,
                'amenities' => $request->amenities ? json_encode($request->amenities) : $room->amenities,
                'meta' => $request->meta ? json_encode($request->meta) : $room->meta,
            ]);

            // Handle images
            if ($request->file('images')) {
                foreach ($request->file('images') as $image) {
                    $fileData = $this->uploads($image, 'images/');
                    RoomImage::create(['room_id' => $room->id, 'image' => $fileData['fileName']]);
                }
            }

            // ✅ Handle periods - DELETE ALL OLD and CREATE NEW
            if ($request->has('periods')) {
                $periods = $request->periods;

                // Decode JSON if string
                if (is_string($periods)) {
                    $periods = json_decode($periods, true);
                }

                // 🔥 DELETE ALL existing periods first
                $room->periods()->delete();

                // ✅ CREATE new periods
                if (!empty($periods) && is_array($periods)) {
                    foreach ($periods as $period) {
                        $room->periods()->create([
                            'period_name' => $period['period_name'],
                            'start_date' => $period['start_date'],
                            'end_date' => $period['end_date'],
                            'sale_price' => $period['sale_price'],
                            'cost_price' => $period['cost_price'] ?? null,
                            'agent_price' => $period['agent_price'] ?? null,
                        ]);
                    }
                }
            }

            DB::commit();

            $room->load('periods', 'images', 'hotel');

            return $this->success(new RoomResource($room), 'Successfully updated', 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Room $room)
    {
        $room->delete();

        return $this->success(null, 'Successfully deleted', 200);
    }

    public function forceDelete(string $id)
    {
        $room = Room::onlyTrashed()->find($id);

        if (!$room) {
            return $this->error(null, 'Data not found', 404);
        }

        $room_images = RoomImage::where('room_id', '=', $room->id)->get();

        foreach ($room_images as $room_image) {
            Storage::delete('images/' . $room_image->image);
        }

        RoomImage::where('room_id', $room->id)->delete();

        $room->forceDelete();

        return $this->success(null, 'Successfully deleted', 200);
    }

    public function restore(string $id)
    {
        $room = Room::onlyTrashed()->find($id);

        if (!$room) {
            return $this->error(null, 'Data not found', 404);
        }

        $room->restore();

        return $this->success(null, 'Product is successfully restored');
    }

    public function deleteImage(Room $room, RoomImage $room_image)
    {
        if ($room->id !== $room_image->room_id) {
            return $this->error(null, 'This image is not belongs to the room', 404);
        }

        // Storage::delete('images/' . $room_image->image);

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
                'agent_price' => $period['agent_price'] ?? null,
            ]);

            $array_of_ids[] = $job->id;
        }

        $room->periods->whereNotIn('id', $array_of_ids)->each->delete();
    }

    private function checkIfOverlapped($ranges)
    {
        $overlaps = [];
        for ($i = 0; $i < count($ranges); $i++) {
            for ($j = ($i + 1); $j < count($ranges); $j++) {

                $start = \Carbon\Carbon::parse($ranges[$j]['start_date']);
                $end = \Carbon\Carbon::parse($ranges[$j]['end_date']);

                $start_first = \Carbon\Carbon::parse($ranges[$i]['start_date']);
                $end_first = \Carbon\Carbon::parse($ranges[$i]['end_date']);

                if (\Carbon\Carbon::parse($ranges[$i]['start_date'])->between($start, $end) || \Carbon\Carbon::parse($ranges[$i]['end_date'])->between($start, $end)) {
                    $overlaps[] = $ranges[$j];

                    break;
                }
                if (\Carbon\Carbon::parse($ranges[$j]['start_date'])->between($start_first, $end_first) || \Carbon\Carbon::parse($ranges[$j]['end_date'])->between($start_first, $end_first)) {
                    $overlaps[] = $ranges[$j];

                    break;
                }
            }
        }

        return $overlaps;
    }

    public function incomplete(Request $request)
    {
        $limit = $request->query('limit', 10);

        $columns = Schema::getColumnListing('rooms');
        $excludedColumns = ['id', 'created_at', 'updated_at', 'deleted_at', 'extra_price', 'owner_price', 'name'];
        $columnsToCheck = array_diff($columns, $excludedColumns);

        $query = Room::query();

        if ($request->hotel_id) {
            $query->where('hotel_id', $request->hotel_id);
        }

        if ($request->search) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        // Combine the conditions for columns being null and hotels without images
        $query->where(function ($query) use ($columnsToCheck) {
            // Condition to check for null columns
            $query->where(function ($subQuery) use ($columnsToCheck) {
                foreach ($columnsToCheck as $column) {
                    $subQuery->orWhereNull($column);
                }
            })
            // Condition to check for hotels without images
                ->orWhereDoesntHave('images');
        });

        $data = $query->paginate($limit);

        return $this->success(RoomResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Hotel List');
    }

    public function getRoomFacilities(Room $room)
    {
        $groupedFacilities = $room->roitemsGrouped();

        return $this->success($groupedFacilities, 'Room facilities grouped by category');
    }

    /**
     * Add roitems to room
     */
    public function addRoitems(Request $request, Room $room)
    {
        $request->validate([
            'roitem_ids' => 'required|array',
            'roitem_ids.*' => 'exists:roitems,id'
        ]);

        try {
            $this->attachRoitems($room, $request->roitem_ids);

            return $this->success(
                new RoomResource($room->load(['roitems, roitems.rofacility'])),
                'Roitems added successfully'
            );
        } catch (Exception $e) {
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Remove roitems from room
     */
    public function removeRoitems(Request $request, Room $room)
    {
        $request->validate([
            'roitem_ids' => 'required|array',
            'roitem_ids.*' => 'exists:roitems,id'
        ]);

        try {
            $room->roitems()->detach($request->roitem_ids);

            return $this->success(
                new RoomResource($room->load(['roitems, roitems.rofacility'])),
                'Roitems removed successfully'
            );
        } catch (Exception $e) {
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Hide all rooms for a specific hotel (set is_show_on to "0")
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function hideAllRoomsByHotel(Request $request)
    {
        $request->validate([
            'hotel_id' => 'required|exists:hotels,id'
        ]);

        DB::beginTransaction();

        try {
            $rooms = Room::where('hotel_id', $request->hotel_id)->get();

            $updatedCount = 0;

            foreach ($rooms as $room) {
                $meta = $room->meta ? json_decode($room->meta, true) : [];

                // Update is_show_on to "0"
                $meta['is_show_on'] = "0";

                $room->update([
                    'meta' => json_encode($meta)
                ]);

                $updatedCount++;
            }

            DB::commit();

            return $this->success([
                'updated_count' => $updatedCount,
                'hotel_id' => $request->hotel_id
            ], "Successfully hidden {$updatedCount} rooms for hotel ID {$request->hotel_id}");

        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Copy images from source room to target room (no images)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function copyRoomImages(Request $request)
    {
        $request->validate([
            'source_room_id' => 'required|exists:rooms,id',
            'target_room_id' => 'required|exists:rooms,id'
        ]);

        DB::beginTransaction();

        try {
            $sourceRoom = Room::with('images')->findOrFail($request->source_room_id);
            $targetRoom = Room::with('images')->findOrFail($request->target_room_id);

            // Check if source room has images
            if ($sourceRoom->images->isEmpty()) {
                return $this->error(null, 'Source room has no images to copy', 400);
            }

            // Optional: Check if target room already has images
            if ($targetRoom->images->isNotEmpty()) {
                return $this->error(null, 'Target room already has images. Please remove them first.', 400);
            }

            $copiedImages = [];

            foreach ($sourceRoom->images as $sourceImage) {
                // Create new image record for target room with same image path
                $newImage = RoomImage::create([
                    'room_id' => $targetRoom->id,
                    'image' => $sourceImage->image
                ]);

                $copiedImages[] = $newImage;
            }

            DB::commit();

            // ✅ FIX: Calculate count outside the string
            $imageCount = count($copiedImages);

            return $this->success([
                'source_room_id' => $sourceRoom->id,
                'source_room_name' => $sourceRoom->name,
                'target_room_id' => $targetRoom->id,
                'target_room_name' => $targetRoom->name,
                'copied_images_count' => $imageCount,
                'images' => $copiedImages
            ], "Successfully copied {$imageCount} images from {$sourceRoom->name} to {$targetRoom->name}");

        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function getRoomsWithImages(Request $request)
    {
        $request->validate([
            'hotel_id' => 'required|exists:hotels,id'
        ]);

        try {
            $roomsWithImages = Room::where('hotel_id', $request->hotel_id)
                ->whereHas('images')
                ->with(['images', 'hotel'])
                ->get();

            return $this->success([
                'count' => $roomsWithImages->count(),
                'rooms' => RoomResource::collection($roomsWithImages)
            ], "Found {$roomsWithImages->count()} rooms with images");

        } catch (Exception $e) {
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
