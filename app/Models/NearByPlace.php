<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NearByPlace extends Model
{
    use HasFactory;

    protected $fillable = [
        'placeable_id',
        'placeable_type',
        'category',
        'sub_category',
        'name',
        'distance',
        'distance_value',
        'distance_unit',
        'walking_time',
        'driving_time',
        'icon',
        'order',
        'is_active',
    ];

    protected $casts = [
        'walking_time' => 'integer',
        'driving_time' => 'integer',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function placeable()
    {
        return $this->morphTo();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
