<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestinationStoreRequest;
use App\Http\Resources\DestinationResource;
use App\Imports\DestinationImport;
use App\Models\Destination;
use App\Models\ProductImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DestinationController extends Controller
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

        $query = Destination::query();

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $data = $query->paginate($limit);

        return $this->success(DestinationResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Destination List');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DestinationStoreRequest $request)
    {
        $input = $request->validated();

        if ($request->file('feature_img')) {
            $input['feature_img'] = uploadFile($request->file('feature_img'), 'images/destination/');
        }

        $destination = Destination::create($input);

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                $file_name = uploadFile($image, 'images/destination/');

                ProductImage::create([
                    'ownerable_id' => $destination->id,
                    'ownerable_type' => Destination::class,
                    'image' => $file_name
                ]);
            };
        }

        return $this->success(new DestinationResource($destination), 'Successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = Destination::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new DestinationResource($find), 'Destination Detail');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(DestinationStoreRequest $request, string $id)
    {
        $find = Destination::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $input = $request->validated();

        if ($request->file('feature_img')) {
            $input['feature_img'] = uploadFile($request->file('feature_img'), 'images/destination/');

            if ($find->feature_img) {
                Storage::delete('public/images/destination/' . $find->feature_img);
            }
        }

        $find->update($input);

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                // Delete existing images
                if (count($find->images) > 0) {
                    foreach ($find->images as $exImage) {
                        // Delete the file from storage
                        Storage::delete('public/images/destination/' . $exImage->image);
                        // Delete the image from the database
                        $exImage->delete();
                    }
                }

                $file_name = uploadFile($image, 'images/destination/');

                ProductImage::create([
                    'ownerable_id' => $find->id,
                    'ownerable_type' => Destination::class,
                    'image' => $file_name
                ]);
            };
        }

        return $this->success(new DestinationResource($find), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $find = Destination::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->images()->delete();
        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function deleteImage($destination_id, $product_image_id)
    {
        $destination = Destination::find($destination_id);
        if (!$destination) {
            return $this->error(null, 'Data not found', 404);
        }

        $product_image = $destination->images()->find($product_image_id);
        if (!$product_image) {
            return $this->error(null, 'Data not found', 404);
        }

        if ($destination->images()->where('id', $product_image->id)->exists() == false) {
            return $this->error(null, 'Invalid destination image', 404);
        }

        Storage::delete('public/images/destination' . $product_image->image);

        $product_image->delete();

        return $this->success(null, 'Destination image is successfully deleted');
    }

    public function uploadImage($destination_id, Request $request)
    {
        $destination = Destination::find($destination_id);
        if (!$destination) {
            return $this->error(null, 'Data not found', 404);
        }

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                $file_name = uploadFile($image, 'images/destination/');

                ProductImage::create([
                    'ownerable_id' => $destination->id,
                    'ownerable_type' => Destination::class,
                    'image' => $file_name
                ]);
            };
        }

        return $this->success(new DestinationResource($destination), 'Successfully created');
    }

    public function import(Request $request)
    {
        try {
            $request->validate(['file' => 'required|mimes:csv,txt']);

            \Excel::import(new DestinationImport, $request->file('file'));

            return $this->success(null, 'CSV import is successful');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
