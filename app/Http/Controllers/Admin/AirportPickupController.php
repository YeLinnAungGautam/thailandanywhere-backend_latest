<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAirportPickupRequest;
use App\Http\Requests\UpdateAirportPickupRequest;
use App\Http\Resources\AirportPickupResource;
use App\Models\AirportPickup;
use App\Models\AirportPickupImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AirportPickupController extends Controller
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
        $city_id = $request->query('city_id');
        $car_id = $request->query('car_id');

        $query = AirportPickup::query()
            ->when($search, function ($s_query) use ($search) {
                $s_query->where('name', 'LIKE', "%{$search}%");
            })
            ->when($city_id, function ($ct_query) use ($city_id) {
                $ct_query->whereIn('id', function ($q1) use ($city_id) {
                    $q1->select('airport_pickup_id')->from('airport_pickup_cities')->where('city_id', $city_id);
                });
            })
            ->when($car_id, function ($c_query) use ($car_id) {
                $c_query->whereIn('id', function ($q1) use ($car_id) {
                    $q1->select('airport_pickup_id')->from('airport_pickup_cars')->where('car_id', $car_id);
                });
            })
            ->orderBy('created_at', 'desc');

        $data = $query->paginate($limit);

        return $this->success(AirportPickupResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Airport Pickup List');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAirportPickupRequest $request)
    {

        $data = [
            'name' => $request->name,
            'description' => $request->description,
        ];

        if ($file = $request->file('cover_image')) {
            $fileData = $this->uploads($file, 'images/');
            $data['cover_image'] = $fileData['fileName'];
        }


        $save = AirportPickup::create($data);

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


        if ($request->images) {
            foreach ($request->file('images') as $image) {
                $fileData = $this->uploads($image, 'images/');
                AirportPickupImage::create(['airport_pickup_id' => $save->id, 'image' => $fileData['fileName']]);
            };
        }


        return $this->success(new AirportPickupResource($save), 'Successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = AirportPickup::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new AirportPickupResource($find), 'Airport Pickup Detail');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAirportPickupRequest $request, string $id)
    {
        $find = AirportPickup::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }


        $data = [
            'name' => $request->name ?? $find->name,
            'description' => $request->description ?? $find->description,
        ];

        if ($file = $request->file('cover_image')) {
            $fileData = $this->uploads($file, 'images/');
            $data['cover_image'] = $fileData['fileName'];

            if ($find->cover_image) {
                Storage::delete('images/' . $find->cover_image);
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
                        Storage::delete('images/' . $exImage->image);
                        // Delete the image from the database
                        $exImage->delete();
                    }
                }

                $fileData = $this->uploads($image, 'images/');
                AirportPickupImage::create(['airport_pickup_id' => $find->id, 'image' => $fileData['fileName']]);
            };
        }



        $prices = $request->prices;
        $agent_prices = $request->agent_prices;
        $data = array_combine($request->car_ids, array_map(function ($price, $agent_price) {
            return ['price' => $price, 'agent_price' => $agent_price];
        }, $prices, $agent_prices));


        $find->cars()->sync($data);

        return $this->success(new AirportPickupResource($find), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $find = AirportPickup::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->cars()->detach();
        $find->tags()->detach();
        $find->destinations()->detach();
        $find->cities()->detach();

        Storage::delete('images/' . $find->cover_image);

        foreach ($find->images as $image) {
            // Delete the file from storage
            Storage::delete('images/' . $image->image);
            // Delete the image from the database
            $image->delete();
        }

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }
}
