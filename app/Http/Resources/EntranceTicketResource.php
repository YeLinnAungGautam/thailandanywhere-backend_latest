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
            'hello' => 'this is test',
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
            'lowest_variation_price' => $this->getLowestVariationPrice(),
            'lowest_walk_in_price' => $this->getLowestWalkInPrice(),
            'total_booking_count' => $this->bookingItems()->count(),
            'youtube_link' => is_null($this->youtube_link) ? null : json_decode($this->youtube_link),
            'email' => is_null($this->email) ? null : json_decode($this->email),
            'meta_data' => $this->meta_data ? json_decode($this->meta_data) : null,
            'contract_name' => $this->contract_name,

            'vat_id' => $this->vat_id,
            'vat_name' => $this->vat_name,
            'vat_address' => $this->vat_address,

            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y H:i:s') : null,

            'key_highlights' => $this->whenLoaded('keyHighlights', function () {
                return KeyHighLightResource::collection($this->keyHighlights);
            }),
            'good_to_knows' => $this->whenLoaded('goodToKnows', function () {
                return GoodToKnowResource::collection($this->goodToKnows);
            }),
        ];
    }

    public function getVariations()
    {
        $discount = ticket_discount(); // Use ticket_discount instead of hotel_discount

        return $this->variations->map(function ($variation) use ($discount) {
            // Calculate discount values for each variation
            $price = (float) ($variation->price ?? 0);
            $cost_price = (float) ($variation->cost_price ?? 0);
            $owner_price = (float) ($variation->owner_price ?? 0);

            // Calculate discount_price: (price - cost_price) * discount_rate
            $discount_price = ($price - $cost_price) * $discount;

            // Calculate discount_percent
            if ($owner_price != 0 && $owner_price) {
                $discount_percent = (($owner_price - ($price - $discount_price)) / $owner_price) * 100;
            } else {
                $discount_percent = 0;
            }

            // Calculate selling_price: price - discount_price
            $selling_price = $price - $discount_price;

            // Add image links and discount calculations to variation
            $variation->image_links = ProductImageResource::collection($variation->images);
            $variation->discount_price = round($discount_price, 2);
            $variation->discount_percent = round($discount_percent, 2);
            $variation->selling_price = round($selling_price, 2);

            return $variation;
        });
    }


    protected function getLowestVariationPrice()
    {
        if (!$this->whenLoaded('variations')) {
            return 0;
        }

        $eligibleVariations = $this->variations->filter(function ($variation) {
            // Check if is_add_on is false
            if ($variation->is_add_on == 1 || $variation->is_add_on === true) {
                return false;
            }

            if (is_null($variation->price) || $variation->price <= 0) {
                return false;
            }

            // If meta_data exists, check if is_show is 1
            if ($variation->meta_data) {
                $metaData = is_string($variation->meta_data)
                    ? json_decode($variation->meta_data, true)
                    : $variation->meta_data;

                if (is_array($metaData)) {
                    // Check the first element if meta_data is an array of objects
                    $firstElement = isset($metaData[0]) ? $metaData[0] : $metaData;

                    if (isset($firstElement['is_show'])) {
                        // Handle both string and integer values
                        return $firstElement['is_show'] == '1' || $firstElement['is_show'] === 1;
                    }
                }
            }

            // If meta_data doesn't exist or is_show is not set, include it (only check is_add_on)
            return true;
        });

        if ($eligibleVariations->isEmpty()) {
            return 0;
        }

        $lowestPriceVariation = $eligibleVariations->sortBy('price')->first();
        return $lowestPriceVariation ? ($lowestPriceVariation->price ?? 0) : 0;
    }

    /**
     * Get the lowest walk-in price for variations that are not add-ons and shown
     */
    protected function getLowestWalkInPrice()
    {
        if (!$this->whenLoaded('variations')) {
            return 0;
        }

        $eligibleVariations = $this->variations->filter(function ($variation) {
            // Check if is_add_on is false
            if ($variation->is_add_on == 1 || $variation->is_add_on === true) {
                return false;
            }

            // Check if owner_price is not null
            if (is_null($variation->owner_price)) {
                return false;
            }

            // If meta_data exists, check if is_show is 1
            if ($variation->meta_data) {
                $metaData = is_string($variation->meta_data)
                    ? json_decode($variation->meta_data, true)
                    : $variation->meta_data;

                if (is_array($metaData)) {
                    // Check the first element if meta_data is an array of objects
                    $firstElement = isset($metaData[0]) ? $metaData[0] : $metaData;

                    if (isset($firstElement['is_show'])) {
                        // Handle both string and integer values
                        return $firstElement['is_show'] == '1' || $firstElement['is_show'] === 1;
                    }
                }
            }

            // If meta_data doesn't exist or is_show is not set, include it (only check is_add_on and owner_price)
            return true;
        });

        if ($eligibleVariations->isEmpty()) {
            return 0;
        }

        $lowestWalkInVariation = $eligibleVariations->sortBy('owner_price')->first();
        return $lowestWalkInVariation ? ($lowestWalkInVariation->owner_price ?? 0) : 0;
    }
}
