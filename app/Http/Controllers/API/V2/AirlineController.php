<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\AirlineResource;
use App\Models\Airline;
use Illuminate\Http\Request;

class AirlineController extends Controller
{
    public function index(Request $request)
    {
        $items = Airline::query()
            ->with('tickets')
            ->when($request->search, function ($s_query) use ($request) {
                $s_query->where('name', 'LIKE', "%{$request->search}%");
            })
            ->when($request->max_price, function ($p_query) use ($request) {
                $p_query->where('starting_balance', '<=', $request->max_price);
            })
            ->paginate($request->limit ?? 10);

        return AirlineResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $airline_id)
    {
        if(is_null($airline = Airline::find($airline_id))) {
            return notFoundMessage();
        }

        $airline->load('tickets');

        return success(new AirlineResource($airline));
    }
}
