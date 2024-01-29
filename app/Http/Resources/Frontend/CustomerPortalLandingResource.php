<?php

namespace App\Http\Resources\Frontend;

use App\Http\Resources\HotelResource;
use App\Http\Resources\PrivateVanTourResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPortalLandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $response = [
            'id' => $this->id,
            'name' => $this->name,
        ];

        if($request->product_type === 'private_van_tour') {
            $response['private_van_tours'] = PrivateVanTourResource::collection($this->privateVanTours->where('type', 'van_tour'));
        } elseif($request->product_type === 'hotel') {
            $response['hotels'] = HotelResource::collection($this->hotels->where('type', 'direct_booking'));
        }

        return $response;
    }
}
