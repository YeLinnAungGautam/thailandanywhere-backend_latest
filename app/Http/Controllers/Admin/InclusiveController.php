<?php

namespace App\Http\Controllers\Admin;

use App\Models\GroupTour;
use App\Models\Inclusive;
use App\Models\InclusiveAirlineTicket;
use App\Models\InclusiveHotel;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use App\Models\AirportPickup;
use App\Traits\HttpResponses;
use App\Models\EntranceTicket;
use App\Models\InclusiveImage;
use App\Models\PrivateVanTour;
use App\Models\InclusiveProduct;
use App\Models\InclusiveGroupTour;
use App\Http\Controllers\Controller;
use App\Models\InclusiveAirportPickup;
use App\Models\InclusiveEntranceTicket;
use App\Models\InclusivePrivateVanTour;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\InclusiveResource;
use App\Http\Requests\StoreInclusiveRequest;

class InclusiveController extends Controller
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

        $query = Inclusive::query();

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');

        $query->with(['groupTours','entranceTickets','airportPickups','privateVanTours','airlineTickets','hotels']);

        $data = $query->paginate($limit);

//        return $data;
        return $this->success(InclusiveResource::collection($data)
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
        ];

        if ($request->file('cover_image')) {
            if ($file = $request->file('cover_image')) {
                $fileData = $this->uploads($file, 'images/');
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

        if ($request->file('cover_image')) {
            if ($file = $request->file('cover_image')) {

                Storage::delete('public/images/' . $find->cover_image);

                $fileData = $this->uploads($file, 'images/');
                $find->cover_image = $fileData['fileName'];
            }
        }

        if ($request->file('images')) {
            // foreach ($find->images as $image) {
            //     // Delete the file from storage
            //     Storage::delete('public/images/' . $image->image);
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

            foreach ($request->products as $product) {
                if ($product['product_type'] === 'private_van_tour') {
                    $product = InclusivePrivateVanTour::create([
                        'inclusive_id' => $find->id,
                        'product_id' => $product['product_id'],
                        'car_id' => isset($product['car_id']) ? $product['car_id'] : null
                    ]);
                }
                if ($product['product_type'] === 'group_tour') {
                    $product = InclusiveGroupTour::create([
                        'inclusive_id' => $find->id,
                        'product_id' => $product['product_id'],
                        'car_id' => isset($product['car_id']) ? $product['car_id'] : null
                    ]);
                }
                if ($product['product_type'] === 'entrance_ticket') {
                    $product = InclusiveEntranceTicket::create([
                        'inclusive_id' => $find->id,
                        'product_id' => $product['product_id'],
                        'car_id' => isset($product['car_id']) ? $product['car_id'] : null
                    ]);
                }
                if ($product['product_type'] === 'airport_pickup') {
                    $product = InclusiveAirportPickup::create([
                        'inclusive_id' => $find->id,
                        'product_id' => $product['product_id'],
                        'car_id' => isset($product['car_id']) ? $product['car_id'] : null
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
}
