<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\PrivateVanTourResource;
use App\Models\PrivateVanTour;
use Illuminate\Http\Request;

class PrivateVanTourController extends Controller
{
    public function index(Request $request)
    {
        $query = PrivateVanTour::query()
            ->withCount('bookingItems')
            ->vanTour()
            ->with('cars', 'cities', 'destinations', 'tags', 'images')
            ->when($request->search, fn ($s_query) => $s_query->where('name', 'LIKE', "%{$request->search}%"))
            ->when($request->city_id, function ($query) use ($request) {
                $query->whereIn('id', fn ($q) => $q->select('private_van_tour_id')->from('private_van_tour_cities')->where('city_id', $request->city_id));
            })
            ->when($request->car_id, function ($query) use ($request) {
                $query->whereIn('id', fn ($q) => $q->select('private_van_tour_id')->from('private_van_tour_cars')->where('car_id', $request->car_id));
            })
            ->when($request->price_range, function ($query) use ($request) {
                $prices = explode('-', $request->price_range);
                $query->whereIn('id', fn ($q) => $q->select('private_van_tour_id')->from('private_van_tour_cars')->where('price', '>=', $prices[0])->where('price', '<=', $prices[1]));
            })
            ->when($request->category_ids, function ($query) use ($request) {
                $query->whereIn('id', function ($q) use ($request) {
                    $q->select('private_van_tour_id')
                        ->from('private_van_tour_destinations')
                        ->whereIn('destination_id', function ($qq) use ($request) {
                            $category_ids = explode(',', $request->category_ids);

                            $qq->select('id')
                                ->from('destinations')
                                ->whereIn('category_id', $category_ids);
                        });
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

        return PrivateVanTourResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string|int $private_van_tour_id)
    {
        if (is_null($private_van_tour = PrivateVanTour::find($private_van_tour_id))) {
            return notFoundMessage();
        }

        $private_van_tour->load('cars', 'cities', 'destinations', 'tags', 'images');

        return success(new PrivateVanTourResource($private_van_tour));
    }
}
