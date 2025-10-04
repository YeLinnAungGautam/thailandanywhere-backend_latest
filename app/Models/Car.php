<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'max_person'];

    public function privateVanTours()
    {
        return $this->belongsToMany(PrivateVanTour::class, 'private_van_tour_cars')
            ->withPivot('price', 'agent_price', 'cost')
            ->withTimestamps();
    }
}
