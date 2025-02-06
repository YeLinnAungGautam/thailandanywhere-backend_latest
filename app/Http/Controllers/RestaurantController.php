<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestaurantRequest;
use App\Http\Resources\RestaurantResource;
use App\Models\ProductContract;
use App\Models\ProductImage;
use App\Models\Restaurant;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RestaurantController extends Controller
{
    use ImageManager, HttpResponses;

    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $max_price = (int) $request->query('max_price');
        $city_id = $request->query('city_id');
        $place = $request->query('place');

        $query = Restaurant::query()
            ->with('meals', 'contracts', 'images', 'city')
            ->when($max_price, function ($q) use ($max_price) {
                $q->whereIn('id', function ($q1) use ($max_price) {
                    $q1->select('restaurant_id')
                        ->from('meals')
                        ->where('is_extra', 0)
                        ->where('meal_price', '<=', $max_price);
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
            });

        $data = $query->paginate($limit);

        return $this->success(RestaurantResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Restaurant List');
    }

    public function store(RestaurantRequest $request)
    {
        DB::beginTransaction();

        try {
            $save = Restaurant::create([
                'name' => $request->name,
                'description' => $request->description,
                'full_description' => $request->full_description,
                'full_description_en' => $request->full_description_en,
                'payment_method' => $request->payment_method,
                'bank_name' => $request->bank_name,
                'bank_account_number' => $request->bank_account_number,
                'city_id' => $request->city_id,
                'account_name' => $request->account_name,
                'place' => $request->place,
                'contract_due' => $request->contract_due,
                'location_map_link' => $request->location_map_link,
                'location_map_address' => $request->location_map_address,
            ]);

            $contractArr = [];

            if ($request->file('contracts')) {
                foreach ($request->file('contracts') as $file) {
                    $fileData = $this->uploads($file, 'contracts/');
                    $contractArr[] = [
                        'ownerable_id' => $save->id,
                        'ownerable_type' => Restaurant::class,
                        'file' => $fileData['fileName']
                    ];
                }

                ProductContract::insert($contractArr);
            }

            if ($request->file('images')) {
                foreach ($request->file('images') as $image) {
                    $fileData = $this->uploads($image, 'images/');
                    ProductImage::create([
                        'ownerable_id' => $save->id,
                        'ownerable_type' => Restaurant::class,
                        'image' => $fileData['fileName']
                    ]);
                };
            }

            DB::commit();

            return $this->success(new RestaurantResource($save), 'Restaurant is successfully created', 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function show(Restaurant $restaurant)
    {
        return $this->success(new RestaurantResource($restaurant), 'Restaurant Detail', 200);
    }

    public function update(RestaurantRequest $request, Restaurant $restaurant)
    {
        DB::beginTransaction();

        try {
            $restaurant->update([
                'name' => $request->name ?? $restaurant->name,
                'description' => $request->description ?? $restaurant->description,
                'full_description' => $request->full_description ?? $restaurant->full_description,
                'full_description_en' => $request->full_description_en ?? $restaurant->full_description_en,
                'city_id' => $request->city_id ?? $restaurant->city_id,
                'place' => $request->place ?? $restaurant->place,
                'bank_name' => $request->bank_name ?? $restaurant->bank_name,
                'account_name' => $request->account_name,
                'payment_method' => $request->payment_method ?? $restaurant->payment_method,
                'bank_account_number' => $request->bank_account_number ?? $restaurant->bank_account_number,
                'contract_due' => $request->contract_due,
                'location_map_link' => $request->location_map_link,
                'location_map_address' => $request->location_map_address,
            ]);

            $contractArr = [];

            if ($request->file('contracts')) {
                foreach ($request->file('contracts') as $file) {
                    $fileData = $this->uploads($file, 'contracts/');
                    $contractArr[] = [
                        'ownerable_id' => $restaurant->id,
                        'ownerable_type' => Restaurant::class,
                        'file' => $fileData['fileName']
                    ];
                }

                ProductContract::insert($contractArr);
            }

            if ($request->file('images')) {
                foreach ($request->file('images') as $image) {
                    $fileData = $this->uploads($image, 'images/');
                    ProductImage::create([
                        'ownerable_id' => $restaurant->id,
                        'ownerable_type' => Restaurant::class,
                        'image' => $fileData['fileName']
                    ]);
                };
            }

            DB::commit();

            return $this->success(new RestaurantResource($restaurant), 'Restaurant is successfully updated', 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function destroy(Restaurant $restaurant)
    {
        $restaurant->delete();

        return $this->success(null, 'Restaurant is successfully deleted', 200);
    }

    public function forceDelete(string $id)
    {
        $restaurant = Restaurant::onlyTrashed()->find($id);

        if (!$restaurant) {
            return $this->error(null, 'Data not found', 404);
        }

        foreach ($restaurant->images as $res_image) {
            Storage::delete('images/' . $res_image->image);
        }

        $restaurant->images()->delete();

        $restaurant->forceDelete();

        return $this->success(null, 'Product is completely deleted');
    }

    public function restore(string $id)
    {
        $find = Restaurant::onlyTrashed()->find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->restore();

        return $this->success(null, 'Product is successfully restored');
    }

    public function deleteImage(Restaurant $restaurant, ProductImage $product_image)
    {
        if ($restaurant->images()->where('id', $product_image->id)->exists() == false) {
            return $this->error(null, 'This image is not belongs to this restaurant', 404);
        }

        Storage::delete('images/' . $product_image->image);

        $product_image->delete();

        return $this->success(null, 'Restaurant image is successfully deleted');
    }
}
