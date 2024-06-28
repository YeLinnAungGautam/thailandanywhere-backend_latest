<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    use HasFactory;

    protected $guarded = [];

    const TYPES = [
        'direct_booking' => 'direct_booking',
        'other_booking' => 'other_booking'
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'hotel_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(HotelContract::class, 'hotel_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(HotelImage::class, 'hotel_id');
    }

    public function bookingItems()
    {
        return $this->morphMany(BookingItem::class, 'product');
    }

    public function scopeOwnProduct($query)
    {
        return $query->where('type', self::TYPES['direct_booking']);
    }

    public function facilities()
    {
        return $this->belongsToMany(Facility::class);
    }

    public function scopeDirectBooking($query)
    {
        return $query->where('type', self::TYPES['direct_booking']);
    }
}
