<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationTaxSlip extends Model
{
    protected $guarded = [];

    public function bookingItem()
    {
        return $this->belongsTo(BookingItem::class, 'booking_item_id', 'id');
    }
}
