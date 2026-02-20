<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InclusivePackage extends Model
{
    use HasFactory ,SoftDeletes;

    protected $fillable = [
        'package_name', 'adults', 'children',
        'start_date', 'end_date', 'nights', 'total_days',
        'day_city_map', 'attractions', 'hotels',
        'van_tours', 'ordered_items', 'descriptions',
        'total_cost_price', 'total_selling_price',
        'status', 'created_by',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'day_city_map' => 'array',   // JSON auto decode
        'attractions'  => 'array',
        'hotels'       => 'array',
        'van_tours'    => 'array',
        'ordered_items'=> 'array',
        'descriptions' => 'array',
    ];
}
