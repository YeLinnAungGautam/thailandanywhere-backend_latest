<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerRoomRate extends Model
{
    protected $fillable = [
        'partner_id',
        'room_id',
        'date',
        'stock',
        'discount',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function bookingItems()
    {
        return $this->hasMany(BookingItem::class, 'room_id', 'room_id');
    }
}
