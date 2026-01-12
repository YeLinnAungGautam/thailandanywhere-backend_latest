<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NearByPlace;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class NearByPlaceController extends Controller
{
    use HttpResponses;

    /**
     * Store multiple newly created nearby places at once.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'placeable_type' => 'required|string',
            'placeable_id' => 'required|integer',
            'nearby_places' => 'required|array|min:1|max:20',
            'nearby_places.*.category' => 'required|in:transport,landmarks,essentials,others',
            'nearby_places.*.sub_category' => 'nullable|string|max:255',
            'nearby_places.*.name' => 'required|string|max:255',
            'nearby_places.*.distance' => 'required|string|max:50',
            'nearby_places.*.distance_value' => 'required|numeric|min:0',
            'nearby_places.*.distance_unit' => 'required|in:m,km,mi',
            'nearby_places.*.walking_time' => 'nullable|integer|min:0',
            'nearby_places.*.driving_time' => 'nullable|integer|min:0',
            'nearby_places.*.icon' => 'nullable|string|max:255',
            'nearby_places.*.order' => 'nullable|integer',
            'nearby_places.*.is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {


            return $this->error($validator->errors()->first(), 422);
        }

        $createdPlaces = [];

        DB::beginTransaction();
        try {
            foreach ($request->nearby_places as $index => $placeData) {
                $data = [
                    'placeable_type' => $request->placeable_type,
                    'placeable_id' => $request->placeable_id,
                    'category' => $placeData['category'],
                    'sub_category' => $placeData['sub_category'] ?? null,
                    'name' => $placeData['name'],
                    'distance' => $placeData['distance'],
                    'distance_value' => $placeData['distance_value'],
                    'distance_unit' => $placeData['distance_unit'],
                    'walking_time' => $placeData['walking_time'] ?? null,
                    'driving_time' => $placeData['driving_time'] ?? null,
                    'icon' => $placeData['icon'] ?? null,
                    'order' => $placeData['order'] ?? ($index + 1),
                    'is_active' => $placeData['is_active'] ?? true,
                ];

                $nearByPlace = NearByPlace::create($data);
                $createdPlaces[] = $nearByPlace;
            }

            DB::commit();


            return $this->success($createdPlaces, 'Nearby places created successfully');

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create nearby places',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified nearby place.
     */
    public function update(Request $request, $id)
    {
        $nearByPlace = NearByPlace::find($id);

        if (!$nearByPlace) {


            return $this->error('Nearby place not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'placeable_type' => 'sometimes|required|string',
            'placeable_id' => 'sometimes|required|integer',
            'category' => 'sometimes|required|in:transport,landmarks,essentials,others',
            'sub_category' => 'nullable|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'distance' => 'sometimes|required|string|max:50',
            'distance_value' => 'sometimes|required|numeric|min:0',
            'distance_unit' => 'sometimes|required|in:m,km,mi',
            'walking_time' => 'nullable|integer|min:0',
            'driving_time' => 'nullable|integer|min:0',
            'icon' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {


            return $this->error($validator->errors()->first(), 422);
        }

        $data = $request->only([
            'placeable_type',
            'placeable_id',
            'category',
            'sub_category',
            'name',
            'distance',
            'distance_value',
            'distance_unit',
            'walking_time',
            'driving_time',
            'icon',
            'order',
            'is_active'
        ]);

        $nearByPlace->update($data);



        return $this->success($nearByPlace, 'Nearby place updated successfully');
    }

    /**
     * Remove the specified nearby place.
     */
    public function destroy($id)
    {
        $nearByPlace = NearByPlace::find($id);

        if (!$nearByPlace) {


            return $this->error('Nearby place not found', 404);
        }

        $nearByPlace->delete();



        return $this->success(null, 'Nearby place deleted successfully');
    }

    /**
     * Update order of nearby places.
     */
    public function updateOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'places' => 'required|array|min:1',
            'places.*.id' => 'required|integer|exists:near_by_places,id',
            'places.*.order' => 'required|integer',
        ]);

        if ($validator->fails()) {


            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            foreach ($request->places as $place) {
                NearByPlace::where('id', $place['id'])->update(['order' => $place['order']]);
            }

            DB::commit();



            return $this->success(null, 'Order updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();



            return $this->error('Failed to update order', 500);
        }
    }
}
