<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupTourResource;
use App\Models\GroupTour;
use Illuminate\Http\Request;

class GroupTourController extends Controller
{
    public function index(Request $request)
    {
        $items = GroupTour::query()
            ->with('destinations', 'tags', 'cities', 'images')
            ->when($request->search, function ($s_query) use ($request) {
                $s_query->where('name', 'LIKE', "{$request->search}%");
            })
            ->when($request->city_id, function ($c_query) use ($request) {
                $c_query->whereIn('id', function ($q) use ($request) {
                    $q->select('group_tour_id')->from('group_tour_cities')->where('city_id', $request->city_id);
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->limit ?? 10);

        return GroupTourResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $group_tour_id)
    {
        if (is_null($group_tour = GroupTour::find($group_tour_id))) {
            return notFoundMessage();
        }

        $group_tour->load('destinations', 'tags', 'cities', 'images');

        return success(new GroupTourResource($group_tour));
    }
}
