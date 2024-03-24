<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationPaymentSlip extends Model
{
    protected $guarded = [];

    public function reservationTransaction()
    {
        return $this->belongsTo(ReservationTransaction::class);
    }
}
