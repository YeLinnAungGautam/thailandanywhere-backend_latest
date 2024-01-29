<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Frontend\CustomerPortalLandingResource;
use App\Models\City;
use App\Models\Hotel;
use App\Models\PrivateVanTour;
use App\Models\PrivateVanTourCity;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class PageController extends Controller
{
    use HttpResponses;

    public function __invoke(Request $request)
    {
        $cities = [];
        if($request->product_type === 'private_van_tour') {
            $cities = $this->getPrivateVanTourCities($request);
        } elseif($request->product_type === 'hotel') {
            $cities = $this->getHotelCities($request);
        }

        return CustomerPortalLandingResource::collection($cities)->additional(['result' => 1, 'message' => 'success']);
    }

    private function getPrivateVanTourCities(Request $request)
    {
        $city_ids = [];
        $van_tour_ids = PrivateVanTour::withCount('bookingItems')
            ->ownProduct()
            ->orderBy('booking_items_count', 'desc')
            ->pluck('id')
            ->toArray();

        if(count($van_tour_ids) > 0) {
            $city_ids = PrivateVanTourCity::whereIn('private_van_tour_id', $van_tour_ids)
                ->groupBy('city_id')
                // ->orderByRaw("FIELD(private_van_tour_id , " . implode(',', $van_tour_ids) .") ASC")
                ->pluck('city_id')
                ->toArray();
        }

        $cities = City::with('privateVanTours')
            ->whereIn('id', $city_ids)
            ->orderByRaw("FIELD(id , " . implode(',', $city_ids) .") ASC")
            ->paginate($request->limit ?? 4);

        return $cities;
    }

    private function getHotelCities(Request $request)
    {
        $city_ids = Hotel::withCount('bookingItems')
            ->ownProduct()
            ->orderBy('booking_items_count', 'desc')
            ->pluck('city_id')
            ->toArray();

        $cities = City::with('hotels')
            ->whereIn('id', $city_ids)
            ->orderByRaw("FIELD(id , " . implode(',', $city_ids) .") ASC")
            ->paginate($request->limit ?? 4);

        return $cities;
    }
}
