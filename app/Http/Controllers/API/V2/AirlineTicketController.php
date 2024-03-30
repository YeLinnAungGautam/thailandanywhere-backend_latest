<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\AirlineTicketResource;
use App\Models\AirlineTicket;
use Illuminate\Http\Request;

class AirlineTicketController extends Controller
{
    public function index(Request $request)
    {
        $items = AirlineTicket::query()
            ->with('airline')
            ->when($request->search, function ($s_query) use ($request) {
                $s_query->where('description', 'LIKE', "%{$request->search}%");
            })
            ->when($request->airline_id, function ($a_query) use ($request) {
                $a_query->where('airline_id', $request->airline_id);
            })
            ->paginate($request->limit ?? 10);

        return AirlineTicketResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $airline_ticket_id)
    {
        if(is_null($airline_ticket = AirlineTicket::find($airline_ticket_id))) {
            return notFoundMessage();
        }

        $airline_ticket->load('airline');

        return success(new AirlineTicketResource($airline_ticket));
    }
}
