<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request)
    {
        return CityResource::collection(City::query()->paginate($request->limit ?? 10))->additional(['result' => 1, 'message' => 'success']);
    }
}
