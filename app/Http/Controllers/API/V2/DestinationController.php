<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\DestinationResource;
use App\Http\Resources\PrivateVanTourResource;
use App\Models\Destination;
use App\Models\PrivateVanTour;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index(Request $request)
    {
        $query = Destination::query()
            ->when($request->search, fn ($s_query) => $s_query->where('name', 'LIKE', "{$request->search}%"))
            ->when($request->mapping, function ($query) {
                $query->whereNotNull('latitude')
                      ->whereNotNull('longitude');
            })
            ->when($request->city_id, function ($query) use ($request) {
                $query->where('city_id', $request->city_id);
            });

        return DestinationResource::collection($query->paginate($request->limit ?? 10))
            ->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(Destination $destination)
    {
        return success(new DestinationResource($destination));
    }

    public function getRelatedTours(string $id)
    {
        $destination = Destination::find($id);

        if (is_null($destination)) {
            return failedMessage('Data not found');
        }

        $related_tours = PrivateVanTour::with('tags', 'cities', 'cars', 'images', 'destinations')
            ->vanTour()
            ->whereHas('destinations', function ($query) use ($id) {
                $query->where('destination_id', $id);
            })
            ->inRandomOrder()
            ->paginate(request('limit') ?? 10);

        return PrivateVanTourResource::collection($related_tours)->additional(['result' => 1, 'message' => 'success']);
    }
}
