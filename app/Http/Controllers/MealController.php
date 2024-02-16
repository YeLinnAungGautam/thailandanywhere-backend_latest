<?php

namespace App\Http\Controllers;

use App\Exports\MealExport;
use App\Http\Requests\MealRequest;
use App\Http\Resources\MealResource;
use App\Models\Meal;
use App\Models\ProductImage;
use App\Models\Restaurant;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MealController extends Controller
{
    use ImageManager, HttpResponses;

    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $order_by_price = $request->query('order_by_price');

        $query = Meal::query()->with('images', 'restaurant');

        if($order_by_price) {
            if($order_by_price == 'low_to_high') {
                $query->orderBy('meal_price');
            } elseif($order_by_price == 'high_to_low') {
                $query->orderByDesc('meal_price');
            }
        }

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($request->restaurant_id) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        $data = $query->paginate($limit);

        return $this->success(MealResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Meal List');
    }

    public function store(MealRequest $request)
    {
        DB::beginTransaction();

        try {
            if(false == Restaurant::where('id', $request->restaurant_id)->exists()) {
                return $this->error(null, "Restaurant not found", 500);
            }

            $save = Meal::create([
                'restaurant_id' => $request->restaurant_id,
                'name' => $request->name,
                'cost' => $request->cost,
                'extra_price' => $request->extra_price,
                'meal_price' => $request->meal_price,
                'description' => $request->description,
                'max_person' => $request->max_person,
                'is_extra' => $request->is_extra ?? 0
            ]);

            if ($request->file('images')) {
                foreach ($request->file('images') as $image) {
                    $fileData = $this->uploads($image, 'images/');

                    ProductImage::create([
                        'ownerable_id' => $save->id,
                        'ownerable_type' => Meal::class,
                        'image' => $fileData['fileName']
                    ]);
                };
            }

            DB::commit();

            return $this->success(new MealResource($save), 'Successfully created', 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function show(Meal $meal)
    {
        return $this->success(new MealResource($meal), 'Meal Detail', 200);
    }

    public function update(MealRequest $request, Meal $meal)
    {
        DB::beginTransaction();

        try {
            $meal->update([
                'name' => $request->name ?? $meal->name,
                'restaurant_id' => $request->restaurant_id ?? $meal->restaurant_id,
                'cost' => $request->cost ?? $meal->cost,
                'description' => $request->description ?? $meal->description,
                'extra_price' => $request->extra_price ?? $meal->extra_price,
                'meal_price' => $request->meal_price ?? $meal->meal_price,
                'max_person' => $request->max_person,
                'is_extra' => $request->is_extra ?? 0
            ]);

            if ($request->file('images')) {
                foreach ($request->file('images') as $image) {
                    $fileData = $this->uploads($image, 'images/');
                    ProductImage::create([
                        'ownerable_id' => $meal->id,
                        'ownerable_type' => Meal::class,
                        'image' => $fileData['fileName']
                    ]);
                };
            }

            DB::commit();

            return $this->success(new MealResource($meal), 'Successfully updated', 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return $this->error(null, $e->getMessage(), 401);
        }
    }

    public function destroy(Meal $meal)
    {
        foreach($meal->images as $meal_image) {
            Storage::delete('public/images/' . $meal_image->image);
        }

        $meal->images()->delete();
        $meal->delete();

        return $this->success(null, 'Successfully deleted', 200);
    }

    public function deleteImage(Meal $meal, ProductImage $product_image)
    {
        if ($meal->images()->where('id', $product_image->id)->exists() == false) {
            return $this->error(null, 'This image is not belongs to this meal', 404);
        }

        Storage::delete('public/images/' . $product_image->image);

        $product_image->delete();

        return $this->success(null, 'Image is successfully deleted');
    }

    public function exportCSV(Request $request)
    {
        $file_name = "meal_export_" . date('Y-m-d-H-i-s') . ".csv";

        \Excel::store(new MealExport(), "public/export/" . $file_name);

        return $this->success(['download_link' => get_file_link('export', $file_name)], 'success export', 200);
    }
}
