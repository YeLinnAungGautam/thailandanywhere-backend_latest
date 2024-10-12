<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntranceTicketVariationResource;
use App\Models\EntranceTicketVariation;
use App\Models\ProductImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EntranceTicketVariationController extends Controller
{
    use HttpResponses, ImageManager;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);

        $query = EntranceTicketVariation::query()
            ->when($request->query('search'), function ($query) use ($request) {
                $query->where('name', 'LIKE', "%{$request->query('search')}%");
            })
            ->when($request->entrance_ticket_id, function ($et_query) use ($request) {
                $et_query->where('entrance_ticket_id', $request->entrance_ticket_id);
            })
            ->when($request->query('max_price'), function ($q) use ($request) {
                $max_price = (int) $request->query('max_price');
                $q->where('price', '<=', $max_price);
            });

        $data = $query->paginate($limit);

        return $this->success(EntranceTicketVariationResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Variation List');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        $request->validate(['including_services' => 'nullable|array']);

        try {
            $save = EntranceTicketVariation::create([
                'name' => $request->name,
                'price_name' => $request->price_name,
                'price' => $request->price,
                'cost_price' => $request->cost_price,
                'agent_price' => $request->agent_price,
                'owner_price' => $request->owner_price,
                'is_add_on' => $request->is_add_on,
                'add_on_price' => $request->add_on_price,
                'entrance_ticket_id' => $request->entrance_ticket_id,
                'description' => $request->description,
                'including_services' => $request->including_services ? json_encode($request->including_services) : null,
                'meta_data' => $request->meta_data ? json_encode($request->meta_data) : null,
            ]);


            if ($request->file('images')) {
                foreach ($request->file('images') as $image) {
                    $fileData = $this->uploads($image, 'images/');

                    ProductImage::create([
                        'ownerable_id' => $save->id,
                        'ownerable_type' => EntranceTicketVariation::class,
                        'image' => $fileData['fileName']
                    ]);
                };
            }

            DB::commit();

            return $this->success(new EntranceTicketVariationResource($save), 'Successfully created', 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            return $this->error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(EntranceTicketVariation $entrance_tickets_variation)
    {
        return $this->success(new EntranceTicketVariationResource($entrance_tickets_variation), 'Variation Detail', 200);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EntranceTicketVariation $entrance_tickets_variation)
    {
        DB::beginTransaction();

        $request->validate(['including_services' => 'nullable|array']);

        try {
            $entrance_tickets_variation->update([
                'name' => $request->name ?? $entrance_tickets_variation->name,
                'entrance_ticket_id' => $request->entrance_ticket_id ?? $entrance_tickets_variation->entrance_ticket_id,
                'price_name' => $request->price_name ?? $entrance_tickets_variation->price_name,
                'price' => $request->price ?? $entrance_tickets_variation->price,
                'cost_price' => $request->cost_price ?? $entrance_tickets_variation->cost_price,
                'agent_price' => $request->agent_price ?? $entrance_tickets_variation->agent_price,
                'owner_price' => $request->owner_price ?? $entrance_tickets_variation->owner_price,
                'description' => $request->description ?? $entrance_tickets_variation->description,
                'is_add_on' => $request->is_add_on ?? $entrance_tickets_variation->is_add_on,
                'add_on_price' => $request->add_on_price ?? $entrance_tickets_variation->add_on_price,
                'including_services' => $request->including_services ? json_encode($request->including_services) : $entrance_tickets_variation->including_services,
                'meta_data' => $request->meta_data ? json_encode($request->meta_data) : $entrance_tickets_variation->meta_data,
            ]);

            if ($request->file('images')) {
                foreach ($request->file('images') as $image) {
                    $fileData = $this->uploads($image, 'images/');
                    ProductImage::create([
                        'ownerable_id' => $entrance_tickets_variation->id,
                        'ownerable_type' => EntranceTicketVariation::class,
                        'image' => $fileData['fileName']
                    ]);
                };
            }

            DB::commit();

            return $this->success(new EntranceTicketVariationResource($entrance_tickets_variation), 'Successfully updated', 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return $this->error(null, $e->getMessage(), 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EntranceTicketVariation $entrance_tickets_variation)
    {
        $entrance_tickets_variation->delete();

        return $this->success(null, 'Successfully deleted', 200);
    }

    public function forceDelete(string $id)
    {
        $entrance_tickets_variation = EntranceTicketVariation::onlyTrashed()->find($id);

        if (!$entrance_tickets_variation) {
            return $this->error(null, 'Data not found', 404);
        }

        foreach ($entrance_tickets_variation->images as $variation_image) {
            Storage::delete('public/images/' . $variation_image->image);
        }

        $entrance_tickets_variation->images()->delete();

        $entrance_tickets_variation->forceDelete();

        return $this->success(null, 'Product is completely deleted');
    }

    public function restore(string $id)
    {
        $find = EntranceTicketVariation::onlyTrashed()->find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->restore();

        return $this->success(null, 'Product is successfully restored');
    }

    public function deleteImage(string $entrance_tickets_variation_id, string $product_image_id)
    {
        $entrance_tickets_variation = EntranceTicketVariation::find($entrance_tickets_variation_id);

        if (!$entrance_tickets_variation) {
            return $this->error(null, 'Invalid entrance variation', 404);
        }

        $product_image = ProductImage::find($product_image_id);

        if (!$product_image) {
            return $this->error(null, 'Invalid image', 404);
        }

        if ($entrance_tickets_variation->images()->where('id', $product_image->id)->exists() == false) {
            return $this->error(null, 'This image is not belongs to this variation', 404);
        }

        Storage::delete('public/images/' . $product_image->image);

        $product_image->delete();

        return $this->success(null, 'Image is successfully deleted');
    }
}
