<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\PrivateVanTourResource;
use App\Models\PrivateVanTour;
use Illuminate\Http\Request;

class PrivateVanTourController extends Controller
{
    public function index(Request $request)
    {
        $items = PrivateVanTour::with('cars', 'cities', 'destinations', 'tags', 'images')->paginate($request->limit ?? 10);

        return PrivateVanTourResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $private_van_tour_id)
    {
        if(is_null($private_van_tour = PrivateVanTour::find($private_van_tour_id))) {
            return notFoundMessage();
        }

        $private_van_tour->load('cars', 'cities', 'destinations', 'tags', 'images');

        return success(new PrivateVanTourResource($private_van_tour));
    }
}
