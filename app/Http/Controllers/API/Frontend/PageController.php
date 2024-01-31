<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Frontend\CustomerPortalLandingResource;
use App\Http\Resources\HotelResource;
use App\Http\Resources\PrivateVanTourResource;
use App\Models\City;
use App\Services\Frontend\PageDataService;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class PageController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $cities = PageDataService::getCities($request);

        return CustomerPortalLandingResource::collection($cities)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string $id, Request $request)
    {
        $city = City::find($id);

        if(is_null($city)) {
            if(is_null($city)) {
                return $this->error(null, 'Data not found', 404);
            }
        }

        if($request->product_type == 'private_van_tour') {
            return $this->vanTourListResponse($city, $request);
        } elseif($request->product_type == 'hotel') {
            return $this->hotelListResponse($city, $request);
        }

        return $this->error(null, 'Data not found', 404);
    }

    private function vanTourListResponse(City $city, Request $request)
    {
        $products = $city->privateVanTours()
            ->when($request->destination_id, function ($q) use ($request) {
                $q->whereHas('destinations', function ($qq) use ($request) {
                    $qq->where('destination_id', $request->destination_id);
                });
            })
            ->paginate($request->limit ?? 10);

        return PrivateVanTourResource::collection($products)->additional([
            'result' => 1,
            'message' => 'success',
            'meta' => [
                'city_id' => $city->id,
                'city_name' => $city->name
            ],
        ]);
    }

    private function hotelListResponse(City $city, Request $request)
    {
        $products = $city->hotels()->paginate($request->limit ?? 10);

        return HotelResource::collection($products)->additional([
            'result' => 1,
            'message' => 'success',
            'meta' => [
                'city_id' => $city->id,
                'city_name' => $city->name
            ],
        ]);
    }
}
