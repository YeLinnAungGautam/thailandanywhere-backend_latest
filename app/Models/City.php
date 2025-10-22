<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'image', 'latitude', 'longitude', 'radius_km'];

    public function privateVanTours()
    {
        return $this->belongsToMany(PrivateVanTour::class, 'private_van_tour_cities', 'city_id', 'private_van_tour_id');
    }

    public function hotels()
    {
        return $this->hasMany(Hotel::class);
    }
}
