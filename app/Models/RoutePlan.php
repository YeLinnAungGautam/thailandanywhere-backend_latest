<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoutePlan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'route_plans';

    protected $guarded = [];

    protected $casts = [
        'destination_ids' => 'array',
        'city_ids' => 'array',
        'other_photos' => 'array',
    ];

    public function privateVanTours()
    {
        return $this->belongsToMany(
            PrivateVanTour::class,
            'private_van_tour_route_plans',
            'route_plan_id',
            'private_van_tour_id'
        )->withTimestamps();
    }

    /**
     * Destinations grouped under this route plan (one Route Plan -> many Destinations).
     */
    public function destinations()
    {
        return Destination::query()->whereIn('id', $this->destination_ids ?? []);
    }

    /**
     * Cities grouped under this route plan (one Route Plan -> many Cities).
     */
    public function cities()
    {
        return City::query()->whereIn('id', $this->city_ids ?? []);
    }
}
