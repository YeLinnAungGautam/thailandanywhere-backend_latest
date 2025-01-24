<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItemResource;
use App\Models\BookingItem;

class ReservationController extends Controller
{
    public function reservationInformation(string $id)
    {
        $find = BookingItem::find($id);

        if (!$find) {
            return failedMessage('Booking Item not found');
        }

        return success(new BookingItemResource($find), 'Booking Item Detail');
    }
}
