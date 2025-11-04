<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotelRequest;
use App\Http\Requests\UpdateHotelRequest;
use App\Http\Resources\HotelMapResource;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use App\Models\HotelContract;
use App\Models\HotelImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
        $allowment = $request->query('allowment');

        $query = Hotel::query()
            ->with('rooms', 'hotelPlace')
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
            ->when($allowment !== null, function ($q) use ($allowment) {
                $q->where('allowment', $allowment);
            })
            ->when($request->type, function ($q) use ($request) {
                $q->where('type', $request->type);
            })
            ->when($request->facilities, function ($query) use ($request) {
                $ids = explode(',', $request->facilities);

                $query->whereIn('id', function ($q) use ($ids) {
                    $q->select('hotel_id')->from('facility_hotel')->whereIn('facility_id', $ids);
                });
            })
            ->when($request->category_id, fn ($query) => $query->where('category_id', $request->category_id));

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

    public function listMapAll(Request $request)
    {
        $search = $request->query('search');
        $city_id = $request->query('city_id');
        $place = $request->query('place');

        $hotels = Hotel::select([
            'id',
            'name',
            'latitude',
            'longitude',
            'rating',
            'place',
            'city_id'
        ])
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->when($search, function ($query) use ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        })
        ->when($city_id, function ($query) use ($city_id) {
            $query->where('city_id', $city_id);
        })
        ->when($place, function ($query) use ($place) {
            $query->where('place', $place);
        })
        ->with('images')
        ->get();

        return $this->success(HotelMapResource::collection($hotels), 'All Hotels');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreHotelRequest $request)
    {
        $hotel_nearby_places = [];
        if ($request->nearby_places) {
            foreach ($request->nearby_places as $nearby_place) {
                $file_name = null;

                if (isset($nearby_place['image'])) {
                    $nearby_image_data = $this->uploads($nearby_place['image'], 'images/');

                    // $file_name = $nearby_image_data['fileName'];
                    $file_name = Storage::url('images/' . $nearby_image_data['fileName']);
                }

                $hotel_nearby_places [] = [
                    'name' => $nearby_place['name'],
                    'distance' => $nearby_place['distance'],
                    'image' => $file_name,
                ];
            }
        }

        $official_logo_name = null;
        if ($request->official_logo) {
            $logo_data = $this->uploads($request->official_logo, 'images/');
            $official_logo_name = Storage::url('images/' . $logo_data['fileName']);
        }

        $save = Hotel::create([
            'name' => $request->name,
            'category_id' => $request->category_id ?? null,
            'description' => $request->description,
            'full_description' => $request->full_description,
            'full_description_en' => $request->full_description_en,
            'type' => $request->type ?? Hotel::TYPES['direct_booking'],
            'payment_method' => $request->payment_method,
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'city_id' => $request->city_id,
            'account_name' => $request->account_name,
            'vat_inclusion' => $request->vat_inclusion,
            'place' => $request->place,
            'place_id' => $request->place_id,
            'legal_name' => $request->legal_name,
            'contract_due' => $request->contract_due,
            'location_map_title' => $request->location_map_title,
            'location_map' => $request->location_map,
            'rating' => $request->rating,
            'nearby_places' => json_encode($hotel_nearby_places),
            'youtube_link' => json_encode($request->youtube_link),
            'email' => json_encode($request->email),
            'check_in' => $request->check_in,
            'check_out' => $request->check_out,
            'cancellation_policy' => $request->cancellation_policy,
            'official_address' => $request->official_address,
            'official_phone_number' => $request->official_phone_number,
            'official_logo' => $official_logo_name,
            'official_email' => $request->official_email,
            'official_remark' => $request->official_remark,
            'vat_id' => $request->vat_id,
            'vat_name' => $request->vat_name,
            'vat_address' => $request->vat_address,

            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        $contractArr = [];

        if ($request->file('contracts')) {
            foreach ($request->file('contracts') as $file) {
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

        if ($request->facilities) {
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
        $hotel_nearby_places = [];
        if ($request->nearby_places) {
            foreach ($request->nearby_places as $nearby_place) {
                $file_name = $nearby_place['image'];

                if (isset($nearby_place['image']) && $nearby_place['image'] instanceof \Illuminate\Http\UploadedFile) {
                    $nearby_image_data = $this->uploads($nearby_place['image'], 'images/');

                    // $file_name = $nearby_image_data['fileName'];
                    $file_name = Storage::url('images/' . $nearby_image_data['fileName']);
                }

                $hotel_nearby_places [] = [
                    'name' => $nearby_place['name'],
                    'distance' => $nearby_place['distance'],
                    'image' => $file_name,
                ];
            }
        }

        $official_logo_name = null;
        if ($request->hasFile('official_logo')) {
            $logo_data = $this->uploads($request->official_logo, 'images/');
            $official_logo_name = Storage::url('images/' . $logo_data['fileName']);
        } else {
            // Keep the existing logo if no new one is provided
            $official_logo_name = $hotel->official_logo;
        }

        $hotel->update([
            'name' => $request->name ?? $hotel->name,
            'category_id' => $request->category_id ?? $hotel->category_id,
            'description' => $request->description ?? $hotel->description,
            'full_description' => $request->full_description ?? $hotel->full_description,
            'full_description_en' => $request->full_description_en ?? $hotel->full_description_en,
            'type' => $request->type ?? $hotel->type,
            'city_id' => $request->city_id ?? $hotel->city_id,
            'place' => $request->place ?? $hotel->place,
            'place_id' => $request->place_id ?? $hotel->place_id,
            'bank_name' => $request->bank_name ?? $hotel->bank_name,
            'account_name' => $request->account_name,
            'payment_method' => $request->payment_method ?? $hotel->payment_method,
            'bank_account_number' => $request->bank_account_number ?? $hotel->bank_account_number,
            'legal_name' => $request->legal_name,
            'vat_inclusion' => $request->vat_inclusion,
            'contract_due' => $request->contract_due,
            'location_map_title' => $request->location_map_title ?? $hotel->location_map_title,
            'location_map' => $request->location_map ?? $hotel->location_map,
            'rating' => $request->rating ?? $hotel->rating,
            'nearby_places' => json_encode($hotel_nearby_places),
            'youtube_link' => $request->youtube_link ? json_encode($request->youtube_link) : $hotel->youtube_link,
            'email' => $request->email ? json_encode($request->email) : $hotel->email,
            'check_in' => $request->check_in ?? $hotel->check_in,
            'check_out' => $request->check_out ?? $hotel->check_out,
            'cancellation_policy' => $request->cancellation_policy ?? $hotel->cancellation_policy,
            'official_address' => $request->official_address ?? $hotel->official_address,
            'official_phone_number' => $request->official_phone_number ?? $hotel->official_phone_number,
            'official_logo' => $official_logo_name ?? $hotel->official_logo ,
            'official_email' => $request->official_email ?? $hotel->official_email,
            'official_remark' => $request->official_remark ?? $hotel->official_remark,
            'vat_id' => $request->vat_id ?? $hotel->vat_id,
            'vat_name' => $request->vat_name ?? $hotel->vat_name,
            'vat_address' => $request->vat_address ?? $hotel->vat_address,

            'latitude' => $request->latitude ?? $hotel->latitude,
            'longitude' => $request->longitude ?? $hotel->longitude,
        ]);

        $contractArr = [];

        if ($request->file('contracts')) {
            foreach ($request->file('contracts') as $file) {
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
        $hotel->delete();

        return $this->success(null, 'Successfully deleted', 200);
    }

    public function forceDelete(string $id)
    {
        $hotel = Hotel::onlyTrashed()->find($id);

        if (!$hotel) {
            return $this->error(null, 'Data not found', 404);
        }

        $hotel_images = HotelImage::where('hotel_id', '=', $hotel->id)->get();

        foreach ($hotel_images as $hotel_image) {
            Storage::delete('images/' . $hotel_image->image);
        }

        $hotel->facilities()->detach();

        HotelImage::where('hotel_id', $hotel->id)->delete();

        $hotel->delete();

        return $this->success(null, 'Successfully deleted', 200);
    }

    public function restore(string $id)
    {
        $hotel = Hotel::onlyTrashed()->find($id);

        if (!$hotel) {
            return $this->error(null, 'Data not found', 404);
        }

        $hotel->restore();

        return $this->success(null, 'Product is successfully restored');
    }

    public function deleteImage(Hotel $hotel, HotelImage $hotel_image)
    {
        if ($hotel->id !== $hotel_image->hotel_id) {
            return $this->error(null, 'This image is not belongs to the hotel', 404);
        }

        Storage::delete('images/' . $hotel_image->image);

        $hotel_image->delete();

        return $this->success(null, 'Hotel image is successfully deleted');
    }

    public function deleteContract(Hotel $hotel, HotelContract $hotel_contract)
    {
        if ($hotel->id !== $hotel_contract->hotel_id) {
            return $this->error(null, 'This contract is not belongs to the hotel', 403);
        }

        Storage::delete('contracts/' . $hotel_contract->file);

        $hotel_contract->delete();

        return $this->success(null, 'Hotel contract is successfully deleted');
    }

    public function incomplete(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');

        $columns = Schema::getColumnListing('hotels');
        $excludedColumns = ['id', 'created_at', 'updated_at', 'deleted_at', 'name'];
        $columnsToCheck = array_diff($columns, $excludedColumns);

        $query = Hotel::query();

        // Add search filter if search term is provided
        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Combine the conditions for columns being null and hotels without images
        $query->where(function ($query) use ($columnsToCheck) {
            // Condition to check for null columns
            $query->where(function ($subQuery) use ($columnsToCheck) {
                foreach ($columnsToCheck as $column) {
                    $subQuery->orWhereNull($column);
                }
            })
            // Condition to check for hotels without images
                ->orWhereDoesntHave('images');
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

    public function addSlug(Request $request, $id)
    {
        $hotel = Hotel::find($id);

        if (!$hotel) {
            return $this->error(null, 'Hotel not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'slugs' => 'required|array|min:1',
            'slugs.*' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Clean and prepare slugs
            $slugs = array_map(function ($slug) {
                return strtolower(trim($slug));
            }, $request->slugs);

            // Remove empty values and duplicates
            $slugs = array_values(array_unique(array_filter($slugs)));

            // Update hotel with new slugs (replace all)
            $hotel->update(['slug' => $slugs]);


            $hotel->refresh();

            return $this->success(null, 'Slugs updated successfully');

        } catch (\Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
