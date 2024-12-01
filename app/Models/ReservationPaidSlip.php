<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationPaidSlip extends Model
{
    use HasFactory;

    protected $fillable = ['booking_item_id', 'file', 'amount'];
}
