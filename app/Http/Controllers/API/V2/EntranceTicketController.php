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
        $query = EntranceTicket::query()
            ->withCount('bookingItems')
            ->with('tags', 'cities', 'categories', 'images', 'contracts', 'variations')
            ->when($request->search, function ($query) use ($request) {
                $query->where('name', 'LIKE', "%{$request->search}%");
            })
            ->when($request->category_id, function ($query) use ($request) {
                $query->whereIn('id', function ($q) use ($request) {
                    $q->select('entrance_ticket_id')->from('entrance_ticket_categories')->where('category_id', $request->category_id);
                });
            })
            ->when($request->query('city_id'), function ($c_query) use ($request) {
                $c_query->whereIn('id', function ($q) use ($request) {
                    $q->select('entrance_ticket_id')->from('entrance_ticket_cities')->where('city_id', $request->query('city_id'));
                });
            })
            ->when($request->activities, function ($query) use ($request) {
                $query->whereIn('id', function ($q) use ($request) {
                    $q->select('entrance_ticket_id')
                        ->from('activity_entrance_ticket')
                        ->whereIn('attraction_activity_id', explode(',', $request->activities));
                });
            })
            ->when($request->show_only, function ($query) {
                $query->where('meta_data', 'LIKE', '%"is_show":' . 1 . '%');
            });

        if ($request->order_by) {
            if ($request->order_by == 'top_selling_products') {
                $query = $query->orderBy('booking_items_count', 'desc');
            }
        } else {
            $query = $query->orderBy('created_at', 'desc');
        }

        $items = $query->paginate($limit ?? 10);

        return EntranceTicketResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $entrance_ticket_id)
    {
        if (is_null($entrance_ticket = EntranceTicket::find($entrance_ticket_id))) {
            return notFoundMessage();
        }

        $entrance_ticket->load('tags', 'cities', 'categories', 'images', 'contracts', 'variations');

        return success(new EntranceTicketResource($entrance_ticket));
    }
}
