<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request)
    {
        $cities = City::when($request->search, function ($query) use ($request) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        })
            ->paginate(request('limit') ?? 10);

        return CityResource::collection($cities)->additional(['result' => 1, 'message' => 'success']);
    }
}
