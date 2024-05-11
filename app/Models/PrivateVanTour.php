<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrivateVanTour extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $table = 'private_van_tours';

    const TYPES = [
        'van_tour' => 'van_tour',
        'car_rental' => 'car_rental'
    ];

    public function tags()
    {
        return $this->belongsToMany(ProductTag::class, 'private_van_tour_tags', 'private_van_tour_id', 'product_tag_id');
    }

    public function cities()
    {
        return $this->belongsToMany(City::class, 'private_van_tour_cities', 'private_van_tour_id', 'city_id');
    }

    public function cars()
    {
        return $this->belongsToMany(Car::class, 'private_van_tour_cars')
            ->withPivot('price', 'agent_price')
            ->withTimestamps();
    }

    public function images()
    {
        return $this->hasMany(PrivateVanTourImage::class, 'private_van_tour_id', 'id');
    }

    public function destinations()
    {
        return $this->belongsToMany(Destination::class, 'private_van_tour_destinations', 'private_van_tour_id', 'destination_id');
    }

    public function bookingItems()
    {
        return $this->morphMany(BookingItem::class, 'product');
    }

    public function scopeOwnProduct($query)
    {
        return $query->where('type', self::TYPES['van_tour']);
    }

    public function scopeVanTour($query)
    {
        return $query->where('type', self::TYPES['van_tour']);
    }

    public function scopeCarRental($query)
    {
        return $query->where('type', self::TYPES['car_rental']);
    }
}
