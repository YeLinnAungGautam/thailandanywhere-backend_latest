<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Frontend\DestinationResource;
use App\Models\Destination;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index(Request $request)
    {
        $destinations = Destination::has('privateVanTours')->paginate($request->limit ?? 10);

        return DestinationResource::collection($destinations)->additional(['result' => 1, 'message' => 'success']);
    }
}
