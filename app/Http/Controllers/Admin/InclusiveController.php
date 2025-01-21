<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInclusiveRequest;
use App\Http\Resources\InclusiveDetailResource;
use App\Http\Resources\InclusiveListResource;
use App\Http\Resources\InclusiveResource;
use App\Models\Inclusive;
use App\Models\InclusiveAirlineTicket;
use App\Models\InclusiveAirportPickup;
use App\Models\InclusiveDetail;
use App\Models\InclusiveEntranceTicket;
use App\Models\InclusiveGroupTour;
use App\Models\InclusiveHotel;
use App\Models\InclusiveImage;
use App\Models\InclusivePrivateVanTour;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InclusiveController extends Controller
{

    use ImageManager;
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = Inclusive::query()
            ->with('InclusiveDetails')
            ->when($request->search, function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            });

        $data = $query->orderBy('created_at', 'desc')->paginate($request->limit ?? 10);

        return $this->success(InclusiveListResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Inclusive List');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInclusiveRequest $request)
    {
        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'sku_code' => $request->sku_code,
            'price' => $request->price,
            'agent_price' => $request->agent_price,
            'price_range' => $request->price_range ? json_encode($request->price_range) : null,
            'day' => $request->day,
            'night' => $request->night,
            'product_itenary_material' => $request->product_itenary_material ? json_encode($request->product_itenary_material) : null,
        ];

        if ($request->file('cover_image')) {
            $request->validate([
                'cover_image' => 'nullable|mimes:pdf'
            ]);

            if ($file = $request->file('cover_image')) {
                $fileData = $this->uploads($file, 'pdfs/');
                $data['cover_image'] = $fileData['fileName'];
            }
        }

        $save = Inclusive::create($data);

        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                $fileData = $this->uploads($image, 'images/');
                InclusiveImage::create(['inclusive_id' => $save->id, 'image' => $fileData['fileName']]);
            };
        }

        if ($request->file('overview_files')) {
            foreach ($request->file('overview_files') as $file) {
                $overview_file = $this->uploads($file, 'overview_files/');

                InclusiveImage::create([
                    'inclusive_id' => $save->id,
                    'type' => 'overview_pdf',
                    'image' => $overview_file['fileName']
                ]);
            };
        }

        if ($request->products) {
            foreach ($request->products as $product) {
                if ($product['product_type'] === 'private_van_tour') {
                    $product = InclusivePrivateVanTour::create([
                        'inclusive_id' => $save->id,
                        'product_id' => $product['product_id'],
                        'car_id' => isset($product['car_id']) ? $product['car_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'day' => $product['day'] ?? 1,

                    ]);
                }

                if ($product['product_type'] === 'group_tour') {
                    $product = InclusiveGroupTour::create([
                        'inclusive_id' => $save->id,
                        'product_id' => $product['product_id'],
                        'car_id' => isset($product['car_id']) ? $product['car_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'day' => $product['day'] ?? 1,

                    ]);
                }
                if ($product['product_type'] === 'entrance_ticket') {
                    $product = InclusiveEntranceTicket::create([
                        'inclusive_id' => $save->id,
                        'product_id' => $product['product_id'],
                        'variation_id' => isset($product['variation_id']) ? $product['variation_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'day' => $product['day'] ?? 1,

                    ]);
                }
                if ($product['product_type'] === 'airport_pickup') {
                    $product = InclusiveAirportPickup::create([
                        'inclusive_id' => $save->id,
                        'product_id' => $product['product_id'],
                        'car_id' => isset($product['car_id']) ? $product['car_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'day' => $product['day'] ?? 1,

                    ]);
                }
                if ($product['product_type'] === 'airline_ticket') {
                    $product = InclusiveAirlineTicket::create([
                        'inclusive_id' => $save->id,
                        'product_id' => $product['product_id'],
                        'ticket_id' => isset($product['ticket_id']) ? $product['ticket_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'day' => $product['day'] ?? 1,

                    ]);
                }
                if ($product['product_type'] === 'hotel') {
                    $product = InclusiveHotel::create([
                        'inclusive_id' => $save->id,
                        'product_id' => $product['product_id'],
                        'room_id' => isset($product['room_id']) ? $product['room_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'checkin_date' => $product['checkin_date'] ?? null,
                        'checkout_date' => $product['checkout_date'] ?? null,
                        'day' => $product['day'] ?? 1,

                    ]);
                }

            }
        }

        return $this->success(new InclusiveResource($save), 'Successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = Inclusive::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new InclusiveResource($find), 'Inclusive Detail');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $find = Inclusive::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->name = $request->name ?? $find->name;
        $find->description = $request->description ?? $find->description;
        $find->sku_code = $request->sku_code ?? $find->sku_code;
        $find->price = $request->price ?? $find->price;
        $find->agent_price = $request->agent_price ?? $find->agent_price;
        $find->day = $request->day ?? 1;
        $find->night = $request->night ?? $find->night;
        $find->price_range = $request->price_range ? json_encode($request->price_range) : $find->price_range;
        $find->product_itenary_material = $request->product_itenary_material ? json_encode($request($request->product_itenary_material)) : $find->product_itenary_material;

        if ($request->file('cover_image')) {
            $request->validate([
                'cover_image' => 'nullable|mimes:pdf'
            ]);

            if ($file = $request->file('cover_image')) {
                Storage::delete('pdfs/' . $find->cover_image);

                $fileData = $this->uploads($file, 'pdfs/');
                $find->cover_image = $fileData['fileName'];
            }
        }

        if ($request->file('images')) {
            // foreach ($find->images as $image) {
            //     // Delete the file from storage
            //     Storage::delete('images/' . $image->image);
            //     // Delete the image from the database
            //     $image->delete();
            // }

            foreach ($request->file('images') as $image) {
                $fileData = $this->uploads($image, 'images/');
                InclusiveImage::create(['inclusive_id' => $find->id, 'image' => $fileData['fileName']]);
            };
        }

        $find->update();


        if ($request->products) {

            InclusivePrivateVanTour::where('inclusive_id', $id)->delete();
            InclusiveAirportPickup::where('inclusive_id', $id)->delete();
            InclusiveEntranceTicket::where('inclusive_id', $id)->delete();
            InclusiveGroupTour::where('inclusive_id', $id)->delete();
            InclusiveHotel::where('inclusive_id', $id)->delete();
            InclusiveAirlineTicket::where('inclusive_id', $id)->delete();

            foreach ($request->products as $product) {
                if ($product['product_type'] === 'private_van_tour') {
                    $product = InclusivePrivateVanTour::create([
                        'inclusive_id' => $find->id,
                        'product_id' => $product['product_id'],
                        'car_id' => isset($product['car_id']) ? $product['car_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'day' => $product['day'] ?? 1,
                    ]);
                }

                if ($product['product_type'] === 'group_tour') {
                    $product = InclusiveGroupTour::create([
                        'inclusive_id' => $find->id,
                        'product_id' => $product['product_id'],
                        'car_id' => isset($product['car_id']) ? $product['car_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'day' => $product['day'] ?? 1,
                    ]);
                }
                if ($product['product_type'] === 'entrance_ticket') {
                    $product = InclusiveEntranceTicket::create([
                        'inclusive_id' => $find->id,
                        'product_id' => $product['product_id'],
                        'variation_id' => isset($product['variation_id']) ? $product['variation_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'day' => $product['day'] ?? 1,
                    ]);
                }
                if ($product['product_type'] === 'airport_pickup') {
                    $product = InclusiveAirportPickup::create([
                        'inclusive_id' => $find->id,
                        'product_id' => $product['product_id'],
                        'car_id' => isset($product['car_id']) ? $product['car_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'day' => $product['day'] ?? 1,
                    ]);
                }
                if ($product['product_type'] === 'airline_ticket') {
                    $product = InclusiveAirlineTicket::create([
                        'inclusive_id' => $find->id,
                        'product_id' => $product['product_id'],
                        'ticket_id' => isset($product['ticket_id']) ? $product['ticket_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'day' => $product['day'] ?? 1,
                    ]);
                }
                if ($product['product_type'] === 'hotel') {
                    $product = InclusiveHotel::create([
                        'inclusive_id' => $find->id,
                        'product_id' => $product['product_id'],
                        'room_id' => isset($product['room_id']) ? $product['room_id'] : null,
                        'selling_price' => $product['selling_price'] ?? null,
                        'quantity' => $product['quantity'] ?? null,
                        'cost_price' => $product['cost_price'] ?? null,
                        'checkin_date' => $product['checkin_date'] ?? null,
                        'checkout_date' => $product['checkout_date'] ?? null,
                        'day' => $product['day'] ?? 1,

                    ]);
                }

            }
        }

        return $this->success(new InclusiveResource($find), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $find = Inclusive::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        InclusivePrivateVanTour::where('inclusive_id', $id)->delete();
        InclusiveAirportPickup::where('inclusive_id', $id)->delete();
        InclusiveEntranceTicket::where('inclusive_id', $id)->delete();
        InclusiveGroupTour::where('inclusive_id', $id)->delete();
        InclusiveImage::where('inclusive_id', $id)->delete();

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function storeDetail(string $id, Request $request)
    {
        $inclusive = Inclusive::find($id);

        if (!$inclusive) {
            return $this->error(null, 'Data not found', 404);
        }

        foreach ($request->details as $detail) {
            $inclusive_detail = InclusiveDetail::updateOrCreate(
                [
                    'inclusive_id' => $id,
                    'day_name' => $detail['day_name'],
                ],
                [
                    'title' => $detail['title'],
                    'summary' => $detail['summary'],
                    'summary_mm' => $detail['summary_mm'] ?? null,
                    'meals' => $detail['meals'],
                ]
            );

            if (array_key_exists('image', $detail) && $detail['image']) {
                $fileData = $this->uploads($detail['image'], 'images/');

                $inclusive_detail->update(['image' => $fileData['fileName']]);
            }

            if ($detail['cities']) {
                $inclusive_cities = explode(',', $detail['cities']);

                $inclusive_detail->cities()->sync($inclusive_cities);
            }

            if ($detail['destinations']) {
                $inclusive_destinations = explode(',', $detail['destinations']);

                $inclusive_detail->destinations()->sync($inclusive_destinations);
            }
        }

        return $this->success(InclusiveDetailResource::collection($inclusive->InclusiveDetails), 'Successfully saved');
    }

    // inclusive images delete function add by kaung
    public function deleteImage(Inclusive $inclusive, InclusiveImage $inclusive_image)
    {
        if ($inclusive->id !== $inclusive_image->inclusive_id) {
            return $this->error(null, 'This image is not belongs to the inclusive', 404);
        }

        Storage::delete('images/' . $inclusive_image->image);

        $inclusive_image->delete();

        return $this->success(null, 'Inclusive image is successfully deleted');
    }
}
