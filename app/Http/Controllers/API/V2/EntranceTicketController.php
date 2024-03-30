<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntranceTicketResource;
use App\Models\EntranceTicket;
use Illuminate\Http\Request;

class EntranceTicketController extends Controller
{
    public function index(Request $request)
    {
        $items = EntranceTicket::query()
            ->with('tags', 'cities', 'categories', 'images', 'contracts', 'variations')
            ->when($request->search, function ($query) use ($request) {
                $query->where('name', 'LIKE', "%{$request->search}%");
            })
            ->when($request->query('city_id'), function ($c_query) use ($request) {
                $c_query->whereIn('id', function ($q) use ($request) {
                    $q->select('entrance_ticket_id')->from('entrance_ticket_cities')->where('city_id', $request->query('city_id'));
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($limit ?? 10);

        return EntranceTicketResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $entrance_ticket_id)
    {
        if(is_null($entrance_ticket = EntranceTicket::find($entrance_ticket_id))) {
            return notFoundMessage();
        }

        $entrance_ticket->load('tags', 'cities', 'categories', 'images', 'contracts', 'variations');

        return success(new EntranceTicketResource($entrance_ticket));
    }
}