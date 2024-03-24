<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReservationTransaction extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function bookingItems()
    {
        return $this->belongsToMany(BookingItem::class);
    }

    public function vendorable()
    {
        return $this->morphTo();
    }

    public function reservationPaymentSlips()
    {
        return $this->hasMany(ReservationPaymentSlip::class);
    }
}
