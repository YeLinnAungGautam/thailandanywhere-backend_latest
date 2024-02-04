<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;

class CityController extends Controller
{
    public function index()
    {
        $cities = City::paginate(request('limit') ?? 10);

        return CityResource::collection($cities)->additional(['result' => 1, 'message' => 'success']);
    }
}
