<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use App\Traits\HttpResponses;

class HotelController extends Controller
{
    use HttpResponses;

    public function show(string $id)
    {
        $hotel = Hotel::find($id);

        if(is_null($hotel)) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new HotelResource($hotel));
    }

    public function getRelatedHotels(string $id)
    {
        $hotel = Hotel::find($id);

        if(is_null($hotel)) {
            return $this->error(null, 'Data not found', 404);
        }

        $related_tours = Hotel::with('city', 'rooms', 'contracts', 'images')
            ->ownProduct()
            ->where('id', '<>', $hotel->id)
            ->where('city_id', $hotel->city_id)
            ->inRandomOrder()
            ->paginate(request('limit') ?? 10);

        return HotelResource::collection($related_tours)->additional(['result' => 1, 'message' => 'success']);
    }
}
