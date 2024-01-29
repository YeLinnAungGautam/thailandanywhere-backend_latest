<?php
namespace App\Http\Controllers\API\Frontend;

use App\Http\Resources\PrivateVanTourResource;
use App\Models\PrivateVanTour;
use App\Traits\HttpResponses;

class PrivateVantourController
{
    use HttpResponses;

    public function show(string $id)
    {
        $private_van_tour = PrivateVanTour::find($id);

        if(is_null($private_van_tour)) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new PrivateVanTourResource($private_van_tour));
    }
}
