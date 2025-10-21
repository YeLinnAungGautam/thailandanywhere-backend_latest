<?php

namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use App\Models\HotelContract;
use App\Models\HotelImage;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
            'rooms.images'
        );

        return $this->success(new HotelResource($hotel), 'Hotel Detail', 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Hotel $hotel)
    {
        $hotel_nearby_places = [];
        if ($request->nearby_places) {
            foreach ($request->nearby_places as $nearby_place) {
                $file_name = $nearby_place['image'] ?? null;

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

        // Handle official_logo - only update if new file is provided
        $official_logo_name = $hotel->official_logo;
        if ($request->hasFile('official_logo')) {
            $logo_data = $this->uploads($request->official_logo, 'images/');
            $official_logo_name = Storage::url('images/' . $logo_data['fileName']);
        }

        // Prepare update data - only include fields that are actually in the request
        $updateData = [
            'name' => $request->get('name', $hotel->name),
            'category_id' => $request->get('category_id', $hotel->category_id),
            'description' => $request->get('description', $hotel->description),
            'full_description' => $request->get('full_description', $hotel->full_description),
            'full_description_en' => $request->get('full_description_en', $hotel->full_description_en),
            'type' => $request->get('type', $hotel->type),
            'city_id' => $request->get('city_id', $hotel->city_id),
            'place' => $request->get('place', $hotel->place),
            'place_id' => $request->get('place_id', $hotel->place_id),
            'bank_name' => $request->get('bank_name', $hotel->bank_name),
            'account_name' => $request->get('account_name', $hotel->account_name),
            'payment_method' => $request->get('payment_method', $hotel->payment_method),
            'bank_account_number' => $request->get('bank_account_number', $hotel->bank_account_number),
            'legal_name' => $request->get('legal_name', $hotel->legal_name),
            'vat_inclusion' => $request->get('vat_inclusion', $hotel->vat_inclusion),
            'contract_due' => $request->get('contract_due', $hotel->contract_due),
            'location_map_title' => $request->get('location_map_title', $hotel->location_map_title),
            'location_map' => $request->get('location_map', $hotel->location_map),
            'rating' => $request->get('rating', $hotel->rating),
            'youtube_link' => $request->has('youtube_link') ? json_encode($request->youtube_link) : $hotel->youtube_link,
            'email' => $request->has('email') ? json_encode($request->email) : $hotel->email,
            'check_in' => $request->get('check_in', $hotel->check_in),
            'check_out' => $request->get('check_out', $hotel->check_out),
            'cancellation_policy' => $request->get('cancellation_policy', $hotel->cancellation_policy),
            'official_address' => $request->get('official_address', $hotel->official_address),
            'official_phone_number' => $request->get('official_phone_number', $hotel->official_phone_number),
            'official_logo' => $official_logo_name,
            'official_email' => $request->get('official_email', $hotel->official_email),
            'official_remark' => $request->get('official_remark', $hotel->official_remark),
            'vat_id' => $request->get('vat_id', $hotel->vat_id),
            'vat_name' => $request->get('vat_name', $hotel->vat_name),
            'vat_address' => $request->get('vat_address', $hotel->vat_address),
            'nearby_places' => $request->has('nearby_places') ? json_encode($hotel_nearby_places) : $hotel->nearby_places,

            'latitude' => $request->get('latitude', $hotel->latitude),
            'longitude' => $request->get('longitude', $hotel->longitude),
        ];

        // Remove null values to prevent overwriting with null
        $updateData = array_filter($updateData, function ($value) {
            return $value !== null;
        });

        $hotel->update($updateData);

        // Handle contracts - add new ones without deleting existing
        $contractArr = [];
        if ($request->file('contracts')) {
            foreach ($request->file('contracts') as $file) {
                $fileData = $this->uploads($file, 'contracts/');
                $contractArr[] = [
                    'hotel_id' => $hotel->id,
                    'file' => $fileData['fileName'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($contractArr)) {
                HotelContract::insert($contractArr);
            }
        }

        // Handle images - add new ones without deleting existing
        if ($request->file('images')) {
            foreach ($request->file('images') as $image) {
                $fileData = $this->uploads($image, 'images/');
                HotelImage::create([
                    'hotel_id' => $hotel->id,
                    'image' => $fileData['fileName'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Handle facilities - only sync if facilities are provided in request
        if ($request->has('facilities')) {
            $hotel->facilities()->sync($request->facilities);
        }

        return $this->success(new HotelResource($hotel), 'Successfully updated', 200);
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
        // if ($hotel->id !== $hotel_image->hotel_id) {
        //     return $this->error(null, 'This image is not belongs to the hotel', 404);
        // }

        // if ($request->hasFile('image')) {
        //     // Delete the old image
        //     Storage::delete('images/' . $hotel_image->image);

        //     // Upload the new image
        //     $imageData = $this->uploads($request->file('image'), 'images/');
        //     $hotel_image->image = $imageData['fileName'];
        //     $hotel_image->title = $request->title ?? $hotel_image->title;
        //     $hotel_image->update();
        // }

        // return $this->success($hotel_image, 'Hotel Image Detail', 200);
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

    public function addContract($id, Request $request)
    {
        $request->validate([
            'contract' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120', // max 5MB
        ]);

        if (!$request->hasFile('contract')) {
            return $this->error(null, 'No contract file provided', 400);
        }

        $contractData = $this->uploads($request->file('contract'), 'contracts/');
        HotelContract::create([
            'hotel_id' => $id,
            'file' => $contractData['fileName'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->success(null, 'Contracts uploaded successfully', 201);
    }

    public function addSlug(Request $request, $id)
    {
        $hotel = Hotel::find($id);

        if (!$hotel) {
            return $this->error(null, 'Hotel not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'slugs' => 'required|array|min:1',
            'slugs.*' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Clean and prepare slugs
            $slugs = array_map(function ($slug) {
                return strtolower(trim($slug));
            }, $request->slugs);

            // Remove empty values and duplicates
            $slugs = array_values(array_unique(array_filter($slugs)));

            // Update hotel with new slugs (replace all)
            $hotel->update(['slug' => $slugs]);


            $hotel->refresh();

            return $this->success(null, 'Slugs updated successfully');

        } catch (\Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
