<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeyHighlight extends Model
{
    use HasFactory;

    protected $fillable = [
        'highlightable_id',
        'highlightable_type',
        'title',
        'description_mm',
        'description_en',
        'image_url',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * ဒီ highlight က hotel လား attraction လား
     * Polymorphic relationship
     */
    public function highlightable()
    {
        return $this->morphTo();
    }

    /**
     * Scope: active highlights only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: ordered by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
