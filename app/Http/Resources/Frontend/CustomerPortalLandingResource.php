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
            $response['private_van_tours'] = $this->getPrivateVanTourResource($request);
        } elseif($request->product_type === 'hotel') {
            $response['hotels'] = $this->getHotelResource($request);
        }

        return $response;
    }

    private function getPrivateVanTourResource(Request $request)
    {
        $query = $this->privateVanTours()->ownProduct()
            ->when($request->destination_id, function ($q) use ($request) {
                $q->whereHas('destinations', function ($qq) use ($request) {
                    $qq->where('destination_id', $request->destination_id);
                });
            });

        return PrivateVanTourResource::collection($query->paginate($request->product_limit ?? 10));
    }

    private function getHotelResource(Request $request)
    {
        return HotelResource::collection($this->hotels()->ownProduct()->paginate($request->product_limit ?? 10));
    }
}
