<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use App\Traits\HttpResponses;

class HotelController extends Controller
{
    use HttpResponses;

    public function show(string $id)
    {
        $hotel = Hotel::find($id);

        if(is_null($hotel)) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new HotelResource($hotel));
    }
}
