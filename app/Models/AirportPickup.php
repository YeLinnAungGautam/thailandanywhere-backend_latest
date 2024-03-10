<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AirportPickup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'cover_image'];

    public function tags()
    {
        return $this->belongsToMany(ProductTag::class, 'airport_pickup_tags', 'airport_pickup_id', 'product_tag_id');
    }

    public function cities()
    {
        return $this->belongsToMany(City::class, 'airport_pickup_cities', 'airport_pickup_id', 'city_id');
    }

    public function cars()
    {
        return $this->belongsToMany(Car::class, 'airport_pickup_cars')
            ->withPivot('price', 'agent_price')
            ->withTimestamps();
    }

    public function images()
    {
        return $this->hasMany(AirportPickupImage::class, 'airport_pickup_id', 'id');
    }

    public function destinations()
    {
        return $this->belongsToMany(Destination::class, 'airport_pickup_destinations', 'airport_pickup_id', 'destination_id');
    }
}
