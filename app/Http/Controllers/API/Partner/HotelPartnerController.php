<?php

namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateHotelRequest;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use App\Models\HotelContract;
use App\Models\HotelImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HotelPartnerController extends Controller
{
    use HttpResponses;
    use ImageManager;

    public function show(Hotel $hotel)
    {
        $hotel->load(
            'rooms',
            'city',
            'contracts',
            'images',
            'rooms.images',
            'keyHighlights',
            'goodToKnows',
            'nearByPlaces'
        );

        return $this->success(new HotelResource($hotel), 'Hotel Detail', 200);
    }


    public function update(UpdateHotelRequest $request, Hotel $hotel)
    {
        $hotel_nearby_places = [];
        if ($request->nearby_places) {
            foreach ($request->nearby_places as $nearby_place) {
                $file_name = $nearby_place['image'];

                if (isset($nearby_place['image']) && $nearby_place['image'] instanceof \Illuminate\Http\UploadedFile) {
                    $nearby_image_data = $this->uploads($nearby_place['image'], 'images/');

                    // $file_name = $nearby_image_data['fileName'];
                    $file_name = Storage::url('images/' . $nearby_image_data['fileName']);
                }

                $hotel_nearby_places [] = [
                    'name' => $nearby_place['name'],
                    'distance' => $nearby_place['distance'],
                    'image' => $file_name,
                ];
            }
        }

        $official_logo_name = null;
        if ($request->hasFile('official_logo')) {
            $logo_data = $this->uploads($request->official_logo, 'images/');
            $official_logo_name = Storage::url('images/' . $logo_data['fileName']);
        } else {
            // Keep the existing logo if no new one is provided
            $official_logo_name = $hotel->official_logo;
        }

        $hotel->update([
            'name' => $request->name ?? $hotel->name,
            'category_id' => $request->category_id ?? $hotel->category_id,
            'description' => $request->description ?? $hotel->description,
            'full_description' => $request->full_description ?? $hotel->full_description,
            'full_description_en' => $request->full_description_en ?? $hotel->full_description_en,
            'type' => $request->type ?? $hotel->type,
            'city_id' => $request->city_id ?? $hotel->city_id,
            'place' => $request->place ?? $hotel->place,
            'place_id' => $request->place_id ?? $hotel->place_id,
            'bank_name' => $request->bank_name ?? $hotel->bank_name,
            'account_name' => $request->account_name,
            'payment_method' => $request->payment_method ?? $hotel->payment_method,
            'bank_account_number' => $request->bank_account_number ?? $hotel->bank_account_number,
            'legal_name' => $request->legal_name,
            'vat_inclusion' => $request->vat_inclusion,
            'contract_due' => $request->contract_due,
            'data_checked' => $request->data_checked,
            'data_status' => $request->data_status,
            'location_map_title' => $request->location_map_title ?? $hotel->location_map_title,
            'location_map' => $request->location_map ?? $hotel->location_map,
            'rating' => $request->rating ?? $hotel->rating,
            'nearby_places' => json_encode($hotel_nearby_places),
            'youtube_link' => $request->youtube_link ? json_encode($request->youtube_link) : $hotel->youtube_link,
            'email' => $request->email ? json_encode($request->email) : $hotel->email,
            'check_in' => $request->check_in ?? $hotel->check_in,
            'check_out' => $request->check_out ?? $hotel->check_out,
            'cancellation_policy' => $request->cancellation_policy ?? $hotel->cancellation_policy,
            'child_policy' => $request->child_policy ?? $hotel->child_policy,
            'official_address' => $request->official_address ?? $hotel->official_address,
            'official_phone_number' => $request->official_phone_number ?? $hotel->official_phone_number,
            'official_logo' => $official_logo_name ?? $hotel->official_logo ,
            'official_email' => $request->official_email ?? $hotel->official_email,
            'official_remark' => $request->official_remark ?? $hotel->official_remark,
            'vat_id' => $request->vat_id ?? $hotel->vat_id,
            'vat_name' => $request->vat_name ?? $hotel->vat_name,
            'vat_address' => $request->vat_address ?? $hotel->vat_address,

            'latitude' => $request->latitude ?? $hotel->latitude,
            'longitude' => $request->longitude ?? $hotel->longitude,
        ]);

        $contractArr = [];

        if ($request->file('contracts')) {
            foreach ($request->file('contracts') as $file) {
                $fileData = $this->uploads($file, 'contracts/');
                $contractArr[] = [
                    'hotel_id' => $hotel->id,
                    'file' => $fileData['fileName']
                ];
            }

            HotelContract::insert($contractArr);
        }



        // $hotel->facilities()->sync($request->facilities);
        // ✅ Sync facilities with order
        if ($request->has('facilities')) {
            $this->attachFacilitiesWithOrder($hotel, $request->facilities);
        }


        return $this->success(new HotelResource($hotel), 'Successfully updated', 200);
    }

    private function attachFacilitiesWithOrder($hotel, $facilities)
    {
        // $facilities = [1, 2, 3, 4] or [['id' => 1], ['id' => 2]]

        $syncData = [];

        foreach ($facilities as $index => $facility) {
            $facilityId = is_array($facility) ? $facility['id'] : $facility;

            $syncData[$facilityId] = [
                'order' => $index, // ✅ Use array index as order
            ];
        }

        // Sync will remove old and add new
        $hotel->facilities()->sync($syncData);
    }

    public function deleteImage(Hotel $hotel, HotelImage $hotel_image)
    {
        if ($hotel->id !== $hotel_image->hotel_id) {
            return $this->error(null, 'This image is not belongs to the hotel', 404);
        }

        Storage::delete('images/' . $hotel_image->image);

        $hotel_image->delete();

        return $this->success(null, 'Hotel image is successfully deleted');
    }

    public function allowmentHotel(Hotel $hotel, Request $request)
    {
        $hotel->update([
            'allowment' => $request->allowment,
        ]);

        return $this->success(new HotelResource($hotel), 'Successfully updated', 200);
    }

    public function addImage(Hotel $hotel, Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png|max:2048', // max 2MB
            'title' => 'nullable|string',
        ]);

        if (!$request->hasFile('image')) {
            return $this->error(null, 'No image file provided', 400);
        }

        $imageData = $this->uploads($request->file('image'), 'images/');
        HotelImage::create([
            'hotel_id' => $hotel->id,
            'image' => $imageData['fileName'],
            'title' => $request->title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->success(null, 'Images uploaded successfully', 201);
    }

    public function editImage($id, $hotel_image, Request $request)
    {
        $hotel = Hotel::find($id);
        $hotel_image = HotelImage::find($hotel_image);
        if ($hotel->id !== $hotel_image->hotel_id) {
            return $this->error(null, 'This image is not belongs to the hotel', 403);
        }

        $request->validate([
            'image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048', // max 2MB
            'title' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            // Delete the old image
            Storage::delete('images/' . $hotel_image->image);

            $imageData = $this->uploads($request->file('image'), 'images/');
            $hotel_image->image = $imageData['fileName'];
        }
        $hotel_image->title = $request->title ?? $hotel_image->title;
        $hotel_image->update();

        return $this->success($hotel_image, 'Hotel Image Detail', 200);
    }

    public function deleteContract($id, $cid)
    {
        $hotel = Hotel::find($id);
        $hotel_contract = HotelContract::find($cid);
        if ($hotel->id !== $hotel_contract->hotel_id) {
            return $this->error(null, 'This contract is not belongs to the hotel', 403);
        }

        Storage::delete('contracts/' . $hotel_contract->file);

        $hotel_contract->delete();

        return $this->success(null, 'Hotel contract is successfully deleted');
    }

}
