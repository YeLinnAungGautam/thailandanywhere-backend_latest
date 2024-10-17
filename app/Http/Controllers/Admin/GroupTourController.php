<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupTourRequest;
use App\Http\Requests\UpdateGroupTourRequest;
use App\Http\Resources\GroupTourResource;
use App\Models\GroupTour;
use App\Models\GroupTourImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GroupTourController extends Controller
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

        $query = GroupTour::query()
            ->when($search, function ($s_query) use ($search) {
                $s_query->where('name', 'LIKE', "%{$search}%");
            })
            ->when($city_id, function ($c_query) use ($city_id) {
                $c_query->whereIn('id', function ($q) use ($city_id) {
                    $q->select('group_tour_id')->from('group_tour_cities')->where('city_id', $city_id);
                });
            })
            ->orderBy('created_at', 'desc');

        $data = $query->paginate($limit);

        return $this->success(GroupTourResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Group Tour List');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGroupTourRequest $request)
    {

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'full_description' => $request->full_description,
            'full_description_en' => $request->full_description_en,
            'sku_code' => $request->sku_code,
            'price' => $request->price,
            'cancellation_policy_id' => $request->cancellation_policy_id,
        ];

        if ($file = $request->file('cover_image')) {
            $fileData = $this->uploads($file, 'images/');
            $data['cover_image'] = $fileData['fileName'];
        }

        $save = GroupTour::create($data);

        if ($request->tag_ids) {
            $save->tags()->sync($request->tag_ids);
        }

        if ($request->city_ids) {
            $save->cities()->sync($request->city_ids);
        }

        if ($request->destination_ids) {
            $save->destinations()->sync($request->destination_ids);
        }

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                $fileData = $this->uploads($image, 'images/');
                GroupTourImage::create(['group_tour_id' => $save->id, 'image' => $fileData['fileName']]);
            };
        }


        return $this->success(new GroupTourResource($save), 'Successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = GroupTour::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new GroupTourResource($find), 'Group Tour Detail');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGroupTourRequest $request, string $id)
    {
        $find = GroupTour::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $data = [
            'name' => $request->name ?? $find->name,
            'description' => $request->description ?? $find->description,
            'full_description' => $request->full_description ?? $find->full_description,
            'full_description_en' => $request->full_description_en ?? $find->full_description_en,
            'sku_code' => $request->sku_code ?? $find->sku_code,
            'price' => $request->price ?? $find->price,
            'cancellation_policy_id' => $request->cancellation_policy_id ?? $find->cancellation_policy_id,
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
                GroupTourImage::create(['group_tour_id' => $find->id, 'image' => $fileData['fileName']]);
            };
        }

        return $this->success(new GroupTourResource($find), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $find = GroupTour::find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function forceDelete(string $id)
    {
        $find = GroupTour::onlyTrashed()->find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

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

    public function restore(string $id)
    {
        $find = GroupTour::onlyTrashed()->find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->restore();

        return $this->success(null, 'Product is successfully restored');
    }
}
