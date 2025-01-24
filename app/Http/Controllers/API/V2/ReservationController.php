<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItemResource;
use App\Models\BookingItem;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    //get reservation information
    public function getReservationInformation(string $id)
    {
        $find = BookingItem::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new BookingItemResource($find), 'Booking Item Detail');
    }
}
