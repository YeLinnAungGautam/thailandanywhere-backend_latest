<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HotelListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lowest_room_price = $this->rooms->where('is_extra', 0)->sortBy('room_price')->first()->room_price ?? 0;
        $lowest_walk_in_price = $this->rooms->where('is_extra', 0)->whereNotNull('owner_price')->sortBy('owner_price')->first()->owner_price ?? 0;
        $lowest_cost_price = $this->rooms->where('is_extra', 0)->sortBy('cost')->first()->cost ?? 0;

        $discount_price = ((float)$lowest_room_price - (float)$lowest_cost_price) * 0.75;
        $discount_percent = ((float)$lowest_walk_in_price - (float)$discount_price) / (float)$lowest_walk_in_price * 100;

        $selling_price = (float)$lowest_room_price - (float)$discount_price;

        return [
            'id' => $this->id,
            'name' => $this->name,
            // 'category' => new HotelCategoryResource($this->category),
            'description' => $this->description,
            'full_description' => $this->full_description,
            'full_description_en' => $this->full_description_en,
            'type' => $this->type,
            'legal_name' => $this->legal_name,
            // 'account_name' => $this->account_name,
            // 'contract_due' => $this->contract_due,
            // 'payment_method' => $this->payment_method,
            // 'bank_name' => $this->bank_name,
            // 'bank_account_number' => $this->bank_account_number,
            'city' => new CityResource($this->city),
            'place' => $this->place,
            'hotel_place' => $this->hotelPlace,
            // 'rooms' => HotelRoomResource::collection($this->rooms),
            // 'contacts' => HotelContractResource::collection($this->contracts),
            'images' => HotelImageResource::collection($this->images),
            'facilities' => FacilityResource::collection($this->facilities),
            'lowest_room_price' => $lowest_room_price,
            'lowest_walk_in_price' => $lowest_walk_in_price,
            'lowest_cost_price' => $lowest_cost_price,
            // 'updated_at' => $this->updated_at,
            // 'created_at' => $this->created_at,
            // 'location_map_title' => $this->location_map_title,
            // 'location_map' => $this->location_map,
            'rating' => $this->rating,
            // 'vat_inclusion' => $this->vat_inclusion,
            // 'nearby_places' => is_null($this->nearby_places) ? null : json_decode($this->nearby_places),
            'youtube_link' => is_null($this->youtube_link) ? null : json_decode($this->youtube_link),
            // 'email' => is_null($this->email) ? null : json_decode($this->email),

            'total_booking_count' => $this->bookingItems()->count(),

            'discount_price' => $discount_price,
            'discount_percent' => round($discount_percent),

            'selling_price' => $selling_price,
            'available_room_count' => $this->rooms->where('is_extra', 0)->count(),

            // 'check_in' => $this->check_in,
            // 'check_out' => $this->check_out,
            // 'cancellation_policy' => $this->cancellation_policy,
            // 'official_address' => $this->official_address,
            // 'official_logo' => $this->official_logo? Storage::url('images/'. $this->official_logo) : null,
            // 'official_phone_number' => $this->official_phone_number,
            // 'official_email' => $this->official_email,
            // 'official_remark' => $this->official_remark,
        ];
    }
}
