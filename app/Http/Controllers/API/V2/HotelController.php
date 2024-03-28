<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function index(Request $request)
    {
        $items = Hotel::with(
            'city',
            'rooms',
            'rooms.images',
            'contracts',
            'images',
            'facilities',
        )->paginate($request->limit ?? 10);

        return HotelResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $hotel_id)
    {
        if(is_null($hotel = Hotel::find($hotel_id))) {
            return notFoundMessage();
        }

        $hotel->load(
            'city',
            'rooms',
            'rooms.images',
            'contracts',
            'images',
            'facilities',
        );

        return success(new HotelResource($hotel));
    }
}
