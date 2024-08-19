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
            'cover_image' => $this->cover_image ? config('app.url') . Storage::url('images/' . $this->cover_image) : null,
            'activities' => AttractionActivityResource::collection($this->activities),
            'tags' => PrivateVanTourTagResource::collection($this->tags),
            'cities' => PrivateVanTourCityResource::collection($this->cities),
            'categories' => ProductCategoryResource::collection($this->categories),
            'variations' => $this->getVariations(),
            'images' => $this->images ? PrivateVanTourImageResource::collection($this->images) : null,
            'contacts' => HotelContractResource::collection($this->contracts),
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
            'lowest_variation_price' => $this->variations->where('is_add_on', false)->sortBy('price')->first()->price ?? 0,
            'total_booking_count' => $this->bookingItems()->count()
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
