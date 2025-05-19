<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingItemGroup extends Model
{
    protected $guarded = [];

    protected $casts = [
        'passport_info' => 'array',
    ];

    public function bookingItems()
    {
        return $this->hasMany(BookingItem::class, 'group_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
