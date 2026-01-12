<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodToKnow extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowable_id',
        'knowable_type',
        'title',
        'description_mm',
        'description_en',
        'icon',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * ဒီ info က hotel လား attraction လား
     */
    public function knowable()
    {
        return $this->morphTo();
    }

    /**
     * Scope: active items only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: ordered
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
