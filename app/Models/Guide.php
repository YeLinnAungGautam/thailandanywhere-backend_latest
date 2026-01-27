<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guide extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'licence',
        'contact',
        'image',
        'notes',
        'day_rate',
        'renew_score',
        'is_active',
        'languages',
    ];

    protected $casts = [
        'languages' => 'array',
        'is_active' => 'boolean',
        'renew_score' => 'integer',
    ];

    /**
     * Get the cities (areas) for the guide through guide_areas pivot table.
     */
    public function cities()
    {
        return $this->belongsToMany(City::class, 'guide_areas', 'guide_id', 'city_id')
                    ->withTimestamps();
    }
}
