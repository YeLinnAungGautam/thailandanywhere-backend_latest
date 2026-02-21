<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntranceTicketListResource;
use App\Http\Resources\EntranceTicketResource;
use App\Models\EntranceTicket;
use App\Services\SessionTracker;
use Illuminate\Http\Request;

class EntranceTicketController extends Controller
{
    protected $tracker;

    public function __construct(SessionTracker $tracker)
    {
        $this->tracker = $tracker;
    }

    public function index(Request $request)
    {
        $query = EntranceTicket::query()
            ->withCount('bookingItems')
            ->with('cities', 'categories', 'images', 'variations', 'activities')
            ->when($request->search, function ($query) use ($request) {
                $query->where('name', 'LIKE', "{$request->search}%");
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
            ->when($request->price_range, function ($q) use ($request) {
                $prices = explode('-', $request->price_range);

                if (count($prices) !== 2) return;

                $q->whereIn('id', function ($q1) use ($prices) {
                    $q1->select('entrance_ticket_id')
                        ->from('entrance_ticket_variations')
                        ->where('is_add_on', 0)
                        ->where('price', '>', 0)
                        ->whereNull('deleted_at')
                        ->where(function ($q2) {
                            // No meta_data = include it
                            // meta_data exists but no is_show key = include it
                            // meta_data exists with is_show = 1 = include it
                            $q2->whereNull('meta_data')
                               ->orWhere('meta_data', 'NOT LIKE', '%is_show%')
                               ->orWhere('meta_data', 'LIKE', '%"is_show":1%')
                               ->orWhere('meta_data', 'LIKE', '%"is_show":"1"%');
                        })
                        ->groupBy('entrance_ticket_id')
                        ->havingRaw('MIN(CAST(price AS DECIMAL(10,2))) BETWEEN ? AND ?', [
                            (float) $prices[0],
                            (float) $prices[1],
                        ]);
                });
            })
            ->when($request->show_only, function ($query) {
                $query->where(function ($query) {
                    $query->where('meta_data', 'LIKE', '%"is_show":1%')
                        ->orWhere('meta_data', 'LIKE', '%"is_show":"1"%');
                });
            });

        if ($request->order_by) {
            if ($request->order_by == 'top_selling_products') {
                $query = $query->orderBy('booking_items_count', 'desc');
            }
        } else {
            $query = $query->orderBy('created_at', 'desc');
        }

        $items = $query->paginate($limit ?? 10);

        return EntranceTicketListResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(Request $request, string|int $entrance_ticket_id)
    {
        if (is_null($entrance_ticket = EntranceTicket::find($entrance_ticket_id))) {
            return notFoundMessage();
        }

        $entrance_ticket->load('tags', 'cities', 'categories', 'images', 'contracts', 'variations','keyHighlights',
        'goodToKnows');

        // Auto-track view event
        $sessionHash = $request->attributes->get('tracking_session');
        if ($sessionHash) {
            $this->tracker->trackEvent(
                $sessionHash,
                'view_detail',
                'entrance_ticket',
                $entrance_ticket->id
            );
        }

        return success(new EntranceTicketResource($entrance_ticket));
    }
}
