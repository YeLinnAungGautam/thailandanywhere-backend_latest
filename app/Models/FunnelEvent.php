<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FunnelEvent extends Model
{
    public $timestamps = false; // Only created_at

    protected $fillable = [
        'session_id',
        'product_type',
        'product_id',
        'event_type',
        'event_value',
        'quantity',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'event_value' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function session()
    {
        return $this->belongsTo(UserSession::class, 'session_id');
    }

    // Scopes
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByProductType($query, string $productType)
    {
        return $query->where('product_type', $productType);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Event type scopes
    public function scopeVisits($query)
    {
        return $query->where('event_type', 'visit_site');
    }

    public function scopeViews($query)
    {
        return $query->where('event_type', 'view_detail');
    }

    public function scopeCartAdds($query)
    {
        return $query->where('event_type', 'add_to_cart');
    }

    public function scopeCheckouts($query)
    {
        return $query->where('event_type', 'go_checkout');
    }

    public function scopePurchases($query)
    {
        return $query->where('event_type', 'complete_purchase');
    }
}
