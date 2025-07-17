<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashImageBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_image_id',
        'booking_id',
        'deposit',
        'notes'
    ];

    protected $casts = [
        'deposit' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the cash image that belongs to this attachment
     */
    public function cashImage()
    {
        return $this->belongsTo(CashImage::class);
    }

    /**
     * Get the booking that belongs to this attachment
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
