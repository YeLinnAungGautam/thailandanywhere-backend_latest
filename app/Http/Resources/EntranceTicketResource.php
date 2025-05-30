<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EntranceTicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'place' => $this->place,
            'bank_account_number' => $this->bank_account_number,
            'legal_name' => $this->legal_name,
            'bank_name' => $this->bank_name,
            'payment_method' => $this->payment_method,
            'account_name' => $this->account_name,
            'name' => $this->name,

            'description' => $this->description,
            'full_description' => $this->full_description,
            'full_description_en' => $this->full_description_en,
            'location_map_title' => $this->location_map_title,
            'location_map' => $this->location_map,
            'vat_inclusion' => $this->vat_inclusion,
            'cover_image' => $this->cover_image ? Storage::url('images/' . $this->cover_image) : null,
            'activities' => AttractionActivityResource::collection($this->activities),
            'tags' => PrivateVanTourTagResource::collection($this->tags),
            'cities' => PrivateVanTourCityResource::collection($this->cities),
            'categories' => ProductCategoryResource::collection($this->categories),
            'variations' => $this->getVariations(),
            'images' => $this->images ? PrivateVanTourImageResource::collection($this->images) : null,
            'contacts' => HotelContractResource::collection($this->contracts),
            // 'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            // 'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
            'lowest_variation_price' => $this->whenLoaded('variations') && $this->variations->where('is_add_on', false)->count() > 0
                ? $this->variations->where('is_add_on', false)->sortBy('price')->first()->price ?? 0
                : 0,
            'lowest_walk_in_price' => $this->whenLoaded('variations') && $this->variations->where('is_add_on', false)->whereNotNull('owner_price')->count() > 0
                ? $this->variations->where('is_add_on', false)->whereNotNull('owner_price')->sortBy('owner_price')->first()->owner_price ?? 0
                : 0,
            'total_booking_count' => $this->bookingItems()->count(),
            'youtube_link' => is_null($this->youtube_link) ? null : json_decode($this->youtube_link),
            'email' => is_null($this->email) ? null : json_decode($this->email),
            'meta_data' => $this->meta_data ? json_decode($this->meta_data) : null,
            'contract_name' => $this->contract_name,

            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y H:i:s') : null,
        ];
    }

    public function getVariations()
    {
        return $this->variations->map(function ($variation) {
            $variation->image_links = ProductImageResource::collection($variation->images);

            return $variation;
        });
    }
}
