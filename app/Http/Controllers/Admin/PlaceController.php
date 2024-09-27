<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Place;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $places = Place::with('hotels')
            ->when($request->search, fn ($query) => $query->where('name', 'LIKE', "%{$request->search}%"))
            ->paginate($request->limit ?? 10);

        return $this->success(PlaceResource::collection($places)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($places->total() / $places->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Place List');
    }

    public function store(PlaceRequest $request)
    {
        $place = Place::create($request->validated());

        return success(new PlaceResource($place));
    }

    public function show(Place $place)
    {
        return success(new PlaceResource($place));
    }

    public function update(PlaceRequest $request, Place $place)
    {
        $place->update($request->validated());

        return success(new PlaceResource($place));
    }

    public function destroy(Place $place)
    {
        $place->delete();

        return success('Place deleted successfully');
    }
}
