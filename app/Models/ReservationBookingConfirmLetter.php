<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationBookingConfirmLetter extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'receipt_date' => 'date',
        'service_start_date' => 'date',
        'service_end_date' => 'date',
    ];

}
