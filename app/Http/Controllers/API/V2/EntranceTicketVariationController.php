<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntranceTicketVariationResource;
use App\Models\EntranceTicketVariation;
use Illuminate\Http\Request;

class EntranceTicketVariationController extends Controller
{
    public function index(Request $request)
    {
        $items = EntranceTicketVariation::query()
            ->with('entranceTicket')
            ->when($request->query('search'), function ($query) use ($request) {
                $query->where('name', 'LIKE', "%{$request->query('search')}%");
            })
            ->when($request->entrance_ticket_id, function ($et_query) use ($request) {
                $et_query->where('entrance_ticket_id', $request->entrance_ticket_id);
            })
            ->when($request->query('max_price'), function ($q) use ($request) {
                $max_price = (int) $request->query('max_price');
                $q->where('price', '<=', $max_price);
            })
            ->paginate($limit ?? 10);

        return EntranceTicketVariationResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $entrance_ticket_variation_id)
    {
        if(is_null($entrance_ticket_variation = EntranceTicketVariation::find($entrance_ticket_variation_id))) {
            return notFoundMessage();
        }

        $entrance_ticket_variation->load('entranceTicket');

        return success(new EntranceTicketVariationResource($entrance_ticket_variation));
    }
}
