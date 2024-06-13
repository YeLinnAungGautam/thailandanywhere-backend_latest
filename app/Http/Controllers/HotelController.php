<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotelRequest;
use App\Http\Requests\UpdateHotelRequest;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use App\Models\HotelContract;
use App\Models\HotelImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class HotelController extends Controller
{
    use ImageManager;
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $max_price = (int) $request->query('max_price');
        $city_id = $request->query('city_id');
        $place = $request->query('place');

        $query = Hotel::query()
            ->with('rooms')
            ->when($max_price, function ($q) use ($max_price) {
                $q->whereIn('id', function ($q1) use ($max_price) {
                    $q1->select('hotel_id')
                        ->from('rooms')
                        ->where('is_extra', 0)
                        ->where('room_price', '<=', $max_price);
                });
            })
            ->when($city_id, function ($c_query) use ($city_id) {
                $c_query->where('city_id', $city_id);
            })
            ->when($place, function ($p_query) use ($place) {
                $p_query->where('place', $place);
            })
            ->when($search, function ($s_query) use ($search) {
                $s_query->where('name', 'LIKE', "%{$search}%");
            })
            ->when($request->type, function ($q) use ($request) {
                $q->where('type', $request->type);
            });

        $data = $query->paginate($limit);

        return $this->success(HotelResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Hotel List');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreHotelRequest $request)
    {
        $save = Hotel::create([
            'name' => $request->name,
            'description' => $request->description,
            'full_description' => $request->full_description,
            'type' => $request->type ?? Hotel::TYPES['direct_booking'],
            'payment_method' => $request->payment_method,
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'city_id' => $request->city_id,
            'account_name' => $request->account_name,
            'place' => $request->place,
            'legal_name' => $request->legal_name,
            'contract_due' => $request->contract_due,
            'location_map_title' => $request->location_map_title,
            'location_map' => $request->location_map,
            'rating' => $request->rating,
            'nearby_places' => $request->nearby_places ? json_encode($request->nearby_places) : null
        ]);

        $contractArr = [];

        if($request->file('contracts')) {
            foreach($request->file('contracts') as $file) {
                $fileData = $this->uploads($file, 'contracts/');
                $contractArr[] = [
                    'hotel_id' => $save->id,
                    'file' => $fileData['fileName']
                ];
            }

            HotelContract::insert($contractArr);
        }

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                $fileData = $this->uploads($image, 'images/');
                HotelImage::create(['hotel_id' => $save->id, 'image' => $fileData['fileName']]);
            };
        }

        if($request->facilities) {
            $save->facilities()->attach($request->facilities);
        }

        return $this->success(new HotelResource($save), 'Successfully created', 200);

    }

    /**
     * Display the specified resource.
     */
    public function show(Hotel $hotel)
    {
        $hotel->load(
            'rooms',
            'city',
            'contracts',
            'images',
            'rooms.images'
        );

        return $this->success(new HotelResource($hotel), 'Hotel Detail', 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateHotelRequest $request, Hotel $hotel)
    {
        $hotel->update([
            'name' => $request->name ?? $hotel->name,
            'description' => $request->description ?? $hotel->description,
            'full_description' => $request->full_description ?? $hotel->full_description,
            'type' => $request->type ?? $hotel->type,
            'city_id' => $request->city_id ?? $hotel->city_id,
            'place' => $request->place ?? $hotel->place,
            'bank_name' => $request->bank_name ?? $hotel->bank_name,
            'account_name' => $request->account_name,
            'payment_method' => $request->payment_method ?? $hotel->payment_method,
            'bank_account_number' => $request->bank_account_number ?? $hotel->bank_account_number,
            'legal_name' => $request->legal_name,
            'contract_due' => $request->contract_due,
            'location_map_title' => $request->location_map_title ?? $hotel->location_map_title,
            'location_map' => $request->location_map ?? $hotel->location_map,
            'rating' => $request->rating ?? $hotel->rating,
            'nearby_places' => $request->nearby_places ? json_encode($request->nearby_places) : null
        ]);

        $contractArr = [];

        if($request->file('contracts')) {
            foreach($request->file('contracts') as $file) {
                $fileData = $this->uploads($file, 'contracts/');
                $contractArr[] = [
                    'hotel_id' => $hotel->id,
                    'file' => $fileData['fileName']
                ];
            }

            HotelContract::insert($contractArr);
        }

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                $fileData = $this->uploads($image, 'images/');
                HotelImage::create(['hotel_id' => $hotel->id, 'image' => $fileData['fileName']]);
            };
        }

        $hotel->facilities()->sync($request->facilities);

        return $this->success(new HotelResource($hotel), 'Successfully updated', 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Hotel $hotel)
    {
        $hotel_images = HotelImage::where('hotel_id', '=', $hotel->id)->get();

        foreach($hotel_images as $hotel_image) {
            Storage::delete('public/images/' . $hotel_image->image);
        }

        $hotel->facilities()->detach();

        HotelImage::where('hotel_id', $hotel->id)->delete();

        $hotel->delete();

        return $this->success(null, 'Successfully deleted', 200);
    }

    public function deleteImage(Hotel $hotel, HotelImage $hotel_image)
    {
        if ($hotel->id !== $hotel_image->hotel_id) {
            return $this->error(null, 'This image is not belongs to the hotel', 404);
        }

        Storage::delete('public/images/' . $hotel_image->image);

        $hotel_image->delete();

        return $this->success(null, 'Hotel image is successfully deleted');
    }

    public function incomplete(Request $request)
    {
        $limit = $request->query('limit', 10);

        $columns = Schema::getColumnListing('hotels');
        $excludedColumns = ['id', 'created_at', 'updated_at', 'deleted_at'];
        $columnsToCheck = array_diff($columns, $excludedColumns);

        $query = Hotel::query()
            ->where(function ($query) use ($columnsToCheck) {
                foreach ($columnsToCheck as $column) {
                    $query->orWhereNull($column);
                }
            })
            ->orWhereDoesntHave('images');

        $data = $query->paginate($limit);

        return $this->success(HotelResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Hotel List');
    }
}
