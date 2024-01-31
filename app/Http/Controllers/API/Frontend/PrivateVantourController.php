<?php
namespace App\Http\Controllers\API\Frontend;

use App\Http\Resources\PrivateVanTourResource;
use App\Models\PrivateVanTour;
use App\Traits\HttpResponses;

class PrivateVantourController
{
    use HttpResponses;

    public function show(string $id)
    {
        $private_van_tour = PrivateVanTour::find($id);

        if(is_null($private_van_tour)) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new PrivateVanTourResource($private_van_tour));
    }

    public function getRelatedTours(string $id)
    {
        $private_van_tour = PrivateVanTour::find($id);

        if(is_null($private_van_tour)) {
            return $this->error(null, 'Data not found', 404);
        }

        $destination_ids = $private_van_tour->destinations->pluck('id')->toArray();

        $related_tours = PrivateVanTour::with('tags', 'cities', 'cars', 'images', 'destinations')
            ->whereHas('destinations', function ($query) use ($destination_ids) {
                $query->whereIn('destination_id', $destination_ids);
            })
            ->inRandomOrder()
            ->paginate(request('limit') ?? 10);

        return PrivateVanTourResource::collection($related_tours)->additional(['result' => 1, 'message' => 'success']);
    }
}
