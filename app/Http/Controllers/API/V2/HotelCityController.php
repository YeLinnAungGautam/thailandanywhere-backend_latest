<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelCityResource;
use App\Models\City;
use Illuminate\Http\Request;

class HotelCityController extends Controller
{
    public function __invoke(Request $request)
    {
        $cities = City::query()
            ->with('hotels')
            ->whereIn('id', function ($query) {
                $query->select('city_id')->from('hotels')->groupBy('city_id');
            })
            ->paginate($request->limit ?? 20);

        return HotelCityResource::collection($cities)->additional(['result' => 1, 'message' => 'success']);
    }
}
