<?php

namespace App\Http\Resources\Frontend;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->image ? config('app.url') . Storage::url('images/' . $this->image) : null,
        ];

        if($request->type == 'private_van_tour') {
            $data['products'] = $city->privateVanTours()->paginate($request->limit ?? 10);
        } elseif($request->type == 'hotel') {
            $data['products'] = $city->hotels()->paginate($request->limit ?? 10);
        } else {
            $data['products'] = [];
        }

        return $data;
    }
}
