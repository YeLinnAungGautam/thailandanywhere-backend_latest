<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use Illuminate\Http\Request;

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
                'rooms.images',
                'contracts',
                'images',
                'facilities',
            )
            ->when($request->search, function ($s_query) use ($request) {
                $s_query->where('name', 'LIKE', "%{$request->search}%");
            })
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
                $q->whereIn('id', function ($q1) use ($request) {
                    $prices = explode('-', $request->price_range);

                    $q1->select('hotel_id')
                        ->from('rooms')
                        ->where('is_extra', 0)
                        ->where('room_price', '>=', $prices[0])
                        ->where('room_price', '<=', $prices[1]);
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

        if($request->order_by) {
            if($request->order_by == 'top_selling_products') {
                $query = $query->orderBy('booking_items_count', 'desc');
            }
        } else {
            $query = $query->orderBy('created_at', 'desc');
        }

        $items = $query->paginate($limit ?? 10);

        return HotelResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $hotel_id)
    {
        if(is_null($hotel = Hotel::find($hotel_id))) {
            return notFoundMessage();
        }

        $hotel->load(
            'city',
            'rooms',
            'rooms.images',
            'contracts',
            'images',
            'facilities',
        );

        return success(new HotelResource($hotel));
    }
}
