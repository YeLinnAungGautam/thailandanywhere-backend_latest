<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Frontend\CustomerPortalLandingResource;
use App\Services\Frontend\PageDataService;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class PageController extends Controller
{
    use HttpResponses;

    public function __invoke(Request $request)
    {
        $cities = PageDataService::getCities($request);

        return CustomerPortalLandingResource::collection($cities)
            ->additional(['result' => 1, 'message' => 'success']);
    }


}
