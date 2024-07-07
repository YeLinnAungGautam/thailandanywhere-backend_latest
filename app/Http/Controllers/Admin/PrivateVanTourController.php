<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePrivateVanTourRequest;
use App\Http\Requests\UpdatePrivateVanTourRequest;
use App\Http\Resources\PrivateVanTourResource;
use App\Models\PrivateVanTour;
use App\Models\PrivateVanTourImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrivateVanTourController extends Controller
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

        $query = PrivateVanTour::query()
            ->with('cars')
            ->when($search, function ($s_query) use ($search) {
                $s_query->where('name', 'LIKE', "%{$search}%");
            })
            ->when($max_price, function ($q) use ($max_price) {
                $q->whereIn('id', function ($q1) use ($max_price) {
                    $q1->select('private_van_tour_id')
                        ->from('private_van_tour_cars')
                        ->where('price', '<=', $max_price);
                });
            })
            ->when($request->query('city_id'), function ($c_q) use ($request) {
                $c_q->whereIn('id', function ($qq) use ($request) {
                    $qq->select('private_van_tour_id')->from('private_van_tour_cities')->where('city_id', $request->query('city_id'));
                });
            })
            ->when($request->query('car_id'), function ($cr_q) use ($request) {
                $cr_q->whereIn('id', function ($q) use ($request) {
                    $q->select('private_van_tour_id')->from('private_van_tour_cars')->where('car_id', $request->query('car_id'));
                });
            })
            ->when($request->type, function ($q) use ($request) {
                $q->where('type', $request->type);
            })
            ->orderBy('created_at', 'desc');

        $data = $query->paginate($limit);

        return $this->success(PrivateVanTourResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Private Van Tour List');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePrivateVanTourRequest $request)
    {
        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type ?? PrivateVanTour::TYPES['car_rental'],
            'sku_code' => $request->sku_code,
            'long_description' => $request->long_description,
            'full_description' => $request->full_description,
            'full_description_en' => $request->full_description_en,
        ];

        if ($file = $request->file('cover_image')) {
            $fileData = $this->uploads($file, 'images/');
            $data['cover_image'] = $fileData['fileName'];
        }


        $save = PrivateVanTour::create($data);

        if ($request->tag_ids) {
            $save->tags()->sync($request->tag_ids);
        }

        if ($request->city_ids) {
            $save->cities()->sync($request->city_ids);
        }

        if ($request->destination_ids) {
            $save->destinations()->sync($request->destination_ids);
        }


        $prices = $request->prices;
        $agent_prices = $request->agent_prices;
        $data = array_combine($request->car_ids, array_map(function ($price, $agent_price) {
            return ['price' => $price, 'agent_price' => $agent_price];
        }, $prices, $agent_prices));


        $save->cars()->sync($data);

        if($request->file('images')) {
            foreach ($request->file('images') as $image) {
                $fileData = $this->uploads($image, 'images/');
                PrivateVanTourImage::create(['private_van_tour_id' => $save->id, 'image' => $fileData['fileName']]);
            };

        }

        return $this->success(new PrivateVanTourResource($save), 'Successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = PrivateVanTour::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new PrivateVanTourResource($find), 'Private Van Tour Detail');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePrivateVanTourRequest $request, string $id)
    {
        $find = PrivateVanTour::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $data = [
            'name' => $request->name ?? $find->name,
            'description' => $request->description ?? $find->description,
            'type' => $request->type ?? $find->type,
            'sku_code' => $request->sku_code ?? $find->sku_code,
            'long_description' => $request->long_description ?? $find->long_description,
            'full_description' => $request->full_description ?? $find->full_description,
            'full_description_en' => $request->full_description_en ?? $find->full_description_en,
        ];

        if ($file = $request->file('cover_image')) {
            $fileData = $this->uploads($file, 'images/');
            $data['cover_image'] = $fileData['fileName'];

            if ($find->cover_image) {
                Storage::delete('public/images/' . $find->cover_image);
            }
        }

        $find->update($data);


        if ($request->tag_ids) {
            $find->tags()->sync($request->tag_ids);
        }

        if ($request->city_ids) {
            $find->cities()->sync($request->city_ids);
        }

        if ($request->destination_ids) {
            $find->destinations()->sync($request->destination_ids);
        }

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                // Delete existing images
                if (count($find->images) > 0) {
                    foreach ($find->images as $exImage) {
                        // Delete the file from storage
                        Storage::delete('public/images/' . $exImage->image);
                        // Delete the image from the database
                        $exImage->delete();
                    }
                }

                $fileData = $this->uploads($image, 'images/');
                PrivateVanTourImage::create(['private_van_tour_id' => $find->id, 'image' => $fileData['fileName']]);
            };
        }

        $prices = $request->prices;
        $agent_prices = $request->agent_prices;
        $data = array_combine($request->car_ids, array_map(function ($price, $agent_price) {
            return ['price' => $price, 'agent_price' => $agent_price];
        }, $prices, $agent_prices));


        $find->cars()->sync($data);

        return $this->success(new PrivateVanTourResource($find), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $find = PrivateVanTour::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->cars()->detach();
        $find->tags()->detach();
        $find->destinations()->detach();
        $find->cities()->detach();

        Storage::delete('public/images/' . $find->cover_image);

        foreach ($find->images as $image) {
            // Delete the file from storage
            Storage::delete('public/images/' . $image->image);
            // Delete the image from the database
            $image->delete();
        }

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }
}
