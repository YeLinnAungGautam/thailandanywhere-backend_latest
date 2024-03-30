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
        $items = PrivateVanTour::query()
            ->with('cars', 'cities', 'destinations', 'tags', 'images')
            ->when($request->search, fn ($s_query) => $s_query->where('name', 'LIKE', "%{$request->search}%"))
            ->when($request->city_id, function ($query) use ($request) {
                $query->whereIn('id', fn ($q) => $q->select('private_van_tour_id')->from('private_van_tour_cities')->where('city_id', $request->city_id));
            })
            ->when($request->car_id, function ($query) use ($request) {
                $query->whereIn('id', fn ($q) => $q->select('private_van_tour_id')->from('private_van_tour_cars')->where('car_id', $request->car_id));
            })
            ->paginate($request->limit ?? 10);

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
