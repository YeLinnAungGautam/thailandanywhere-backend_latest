<?php
namespace App\Services\Frontend;

use App\Models\City;
use App\Models\Hotel;
use App\Models\PrivateVanTour;
use App\Models\PrivateVanTourCity;
use Illuminate\Http\Request;

class PageDataService
{
    public static function getCities(Request $request)
    {
        $cities = [];
        if($request->product_type === 'private_van_tour') {
            $cities = self::getPrivateVanTourCities($request);
        } elseif($request->product_type === 'hotel') {
            $cities = self::getHotelCities($request);
        }

        return $cities;
    }

    private static function getPrivateVanTourCities(Request $request)
    {
        $city_ids = [];
        $van_tour_ids = PrivateVanTour::withCount('bookingItems')
            ->ownProduct()
            ->orderBy('booking_items_count', 'desc')
            ->pluck('id')
            ->toArray();

        if(count($van_tour_ids) > 0) {
            $city_ids = PrivateVanTourCity::whereIn('private_van_tour_id', $van_tour_ids)
                ->pluck('city_id')
                ->unique()
                ->toArray();
        }

        $cities = City::with('privateVanTours')
            ->whereIn('id', $city_ids)
            ->orderByRaw("FIELD(id , " . implode(',', $city_ids) .") DESC")
            ->paginate($request->limit ?? 4);

        return $cities;
    }

    private static function getHotelCities(Request $request)
    {
        $city_ids = Hotel::withCount('bookingItems')
            ->ownProduct()
            ->orderBy('booking_items_count', 'desc')
            ->pluck('city_id')
            ->toArray();

        $cities = City::with('hotels')
            ->whereIn('id', $city_ids)
            ->orderByRaw("FIELD(id , " . implode(',', $city_ids) .") ASC")
            ->paginate($request->limit ?? 4);

        return $cities;
    }
}
