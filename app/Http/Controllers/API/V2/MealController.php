<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\MealResource;
use App\Models\Meal;
use Illuminate\Http\Request;

class MealController extends Controller
{
    public function index(Request $request)
    {
        $items = Meal::query()
            ->with('images', 'restaurant')
            ->when($request->order_by_price, function ($query) use ($request) {
                if($request->order_by_price == 'low_to_high') {
                    $query->orderBy('meal_price');
                } elseif($request->order_by_price == 'high_to_low') {
                    $query->orderByDesc('meal_price');
                }
            })
            ->when($request->search, fn ($query) => $query->where('name', 'LIKE', "%{$request->search}%"))
            ->when($request->restaurant_id, fn ($query) => $query->where('restaurant_id', $request->restaurant_id))
            ->paginate($request->limit ?? 10);

        return MealResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $meal_id)
    {
        if(is_null($meal = Meal::find($meal_id))) {
            return notFoundMessage();
        }

        $meal->load('images', 'restaurant');

        return success(new MealResource($meal));
    }
}
