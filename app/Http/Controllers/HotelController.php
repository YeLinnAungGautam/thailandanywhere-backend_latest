<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\HotelImage;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use App\Models\HotelContract;
use App\Traits\HttpResponses;
use App\Http\Resources\HotelResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreHotelRequest;
use App\Http\Requests\UpdateHotelRequest;

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

        $query = Hotel::query();

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

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

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreHotelRequest $request)
    {
        $save = Hotel::create([
             'name' => $request->name,
             'payment_method' => $request->payment_method,
             'bank_name' => $request->bank_name,
             'bank_account_number' => $request->bank_account_number,
             'city_id' => $request->city_id,
             'account_name' => $request->account_name,
             'place' => $request->place,
             'legal_name' => $request->legal_name,
             'contract_due' => $request->contract_due,
         ]);

        $contractArr = [];

        if($request->file('contracts')) {
            foreach($request->file('contracts') as $file) {
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

        return $this->success(new HotelResource($save), 'Successfully created', 200);

    }

    /**
     * Display the specified resource.
     */
    public function show(Hotel $hotel)
    {
        return $this->success(new HotelResource($hotel), 'Hotel Detail', 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateHotelRequest $request, Hotel $hotel)
    {
        $hotel->update([
            'name' => $request->name ?? $hotel->name,
            'city_id' => $request->city_id ?? $hotel->city_id,
            'place' => $request->place ?? $hotel->place,
            'bank_name' => $request->bank_name ?? $hotel->bank_name,
            'account_name' => $request->account_name,
            'payment_method' => $request->payment_method ?? $hotel->payment_method,
            'bank_account_number' => $request->bank_account_number ?? $hotel->bank_account_number,
            'legal_name' => $request->legal_name,
            'contract_due' => $request->contract_due,
        ]);

        $contractArr = [];

        if($request->file('contracts')) {
            foreach($request->file('contracts') as $file) {
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
        }else{
            foreach ($hotel->images as $image) {
                Storage::delete('public/images/' . $image->image);
                $image->delete();
            }
        }

        return $this->success(new HotelResource($hotel), 'Successfully updated', 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Hotel $hotel)
    {
        $hotel_images = HotelImage::where('room_id','=',$room->id)->get();

        foreach($hotel_images as $hotel_image){

            Storage::delete('public/images/' . $hotel_image->image);

        }

        HotelImage::where('room_id',$room->id)->delete();

        $hotel->delete();
        return $this->success(null, 'Successfully deleted', 200);
    }
}
