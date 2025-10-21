<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelListResource;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HotelController extends Controller
{
    public function index(Request $request)
    {
        $query = Hotel::query()
            ->withCount('bookingItems')
            ->directBooking()
            ->with(
                'city',
                'rooms',
                'images',
                'rooms.roomRates',
                // 'rooms.images',
                // 'contracts',
                'facilities',
            )
            // ->when($request->search, function ($s_query) use ($request) {
            //     $s_query->where('name', 'LIKE', "{$request->search}%");
            // })
            ->when($request->max_price, function ($q) use ($request) {
                $q->whereIn('id', function ($q1) use ($request) {
                    $q1->select('hotel_id')
                        ->from('rooms')
                        ->where('is_extra', 0)
                        ->where('room_price', '<=', $request->max_price);
                });
            })
            ->when($request->city_id, function ($c_query) use ($request) {
                $c_query->where('city_id', $request->city_id);
            })
            ->when($request->place, function ($p_query) use ($request) {
                $p_query->where('place', $request->place);
            })
            ->when($request->price_range, function ($q) use ($request) {
                $prices = explode('-', $request->price_range);

                $q->whereIn('id', function ($q1) use ($request, $prices) {
                    $q1->select('hotel_id')
                        ->from('rooms')
                        ->where('is_extra', 0)
                        ->groupBy('hotel_id')
                        ->havingRaw('MIN(room_price) BETWEEN ? AND ?', $prices);
                });
            })
            ->when($request->rating, fn ($query) => $query->where('rating', $request->rating))
            ->when($request->facilities, function ($query) use ($request) {
                $ids = explode(',', $request->facilities);

                $query->whereIn('id', function ($q) use ($ids) {
                    $q->select('hotel_id')->from('facility_hotel')->whereIn('facility_id', $ids);
                });
            })
            ->when($request->category_id, fn ($query) => $query->where('category_id', $request->category_id));

        if ($request->search) {
            $searchTerm = strtolower(trim($request->search));

            $query->where(function ($searchQuery) use ($searchTerm) {
                // Search in hotel name
                $searchQuery->where('name', 'LIKE', $searchTerm . '%')
                // OR search in slug
                    ->orWhereRaw('JSON_SEARCH(slug, "one", ?) IS NOT NULL', ['%' . $searchTerm . '%']);
            });
        }

        if ($request->order_by) {
            if ($request->order_by == 'top_selling_products') {
                $query = $query->orderBy('booking_items_count', 'desc');
            }
        } else {
            $query = $query->orderBy('created_at', 'desc');
        }

        $items = $query->paginate($limit ?? 10);

        return HotelListResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $hotel_id)
    {
        try {
            if (is_null($hotel = Hotel::find($hotel_id))) {
                return notFoundMessage();
            }

            $hotel->load(
                'city',
                'rooms',
                'rooms.roomRates',
                'rooms.images',
                'contracts',
                'images',
                'facilities',
            );

            return success(new HotelResource($hotel));
        } catch (Exception $e) {
            Log::error($e);
            Log::error('Hotel ID: ' . $hotel_id);

            throw new Exception($e->getMessage());
        }
    }
}
