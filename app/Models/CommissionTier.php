<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'min_salary',
        'avg_daily',
        'rate',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_salary'  => 'integer',
        'avg_daily'   => 'integer',
        'rate'        => 'float',
        'sort_order'  => 'integer',
        'is_active'   => 'boolean',
    ];

    /**
     * Only return active tiers, ordered by min_salary ascending.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('min_salary');
    }
}
