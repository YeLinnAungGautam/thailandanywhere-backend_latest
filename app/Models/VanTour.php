<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VanTour extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'van_tours';

    /**
     * Adjust these keys/values to match the actual types you use.
     */
    public const TYPES = [
        'car_rental' => 'car_rental',
        'day_tour' => 'day_tour',
        'ticket' => 'ticket',
    ];

    protected $fillable = [
        'name',
        'sku_code',
        'type',
        'supplier_cost',
    ];

    protected $casts = [
        'supplier_cost' => 'array',
    ];

    public function cities()
    {
        return $this->belongsToMany(City::class, 'van_tour_cities', 'vantour_id', 'city_id')
            ->withTimestamps();
    }

    public function routePlans()
    {
        return $this->belongsToMany(RoutePlan::class, 'van_tour_route_plans', 'vantour_id', 'route_plan_id')
            ->withTimestamps();
    }

    public function cars()
    {
        return $this->belongsToMany(Car::class, 'van_tour_cars', 'vantour_id', 'car_id')
            ->withPivot(['price', 'agent_price', 'cost'])
            ->withTimestamps();
    }
}
