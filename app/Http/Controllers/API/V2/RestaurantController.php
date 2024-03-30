<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function index(Request $request)
    {
        $items = Restaurant::query()
            ->with('meals', 'contracts', 'images', 'city')
            ->when($request->max_price, function ($q) use ($request) {
                $q->whereIn('id', function ($q1) use ($request) {
                    $q1->select('restaurant_id')
                        ->from('meals')
                        ->where('is_extra', 0)
                        ->where('meal_price', '<=', $request->max_price);
                });
            })
            ->when($request->city_id, function ($c_query) use ($request) {
                $c_query->where('city_id', $request->city_id);
            })
            ->when($request->place, function ($p_query) use ($request) {
                $p_query->where('place', $request->place);
            })
            ->when($request->search, function ($s_query) use ($request) {
                $s_query->where('name', 'LIKE', "%{$request->search}%");
            })
            ->paginate($request->limit ?? 10);

        return RestaurantResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $restaurant_id)
    {
        if(is_null($restaurant = Restaurant::find($restaurant_id))) {
            return notFoundMessage();
        }

        $restaurant->load('meals', 'contracts', 'images', 'city');

        return success(new RestaurantResource($restaurant));
    }
}
