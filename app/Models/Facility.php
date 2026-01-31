<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    // Relationship with hotels
    public function hotels()
    {
        return $this->belongsToMany(Hotel::class, 'facility_hotel')
            ->withPivot('order') // ✅ Include pivot order
            ->withTimestamps();
    }

    // Scope for active facilities
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope for ordering
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
