<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    protected $fillable = [
        'session_hash',
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'first_visit_at',
        'last_activity_at',
        'expires_at',
        'is_active',
        'is_bot',
    ];

    protected $casts = [
        'first_visit_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_bot' => 'boolean',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function events()
    {
        return $this->hasMany(FunnelEvent::class, 'session_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('expires_at', '>', now());
    }

    public function scopeNotBot($query)
    {
        return $query->where('is_bot', false);
    }

    public function scopeGuest($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeByDevice($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }
}
