<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function scopeAgentOnly($query)
    {
        $query->where('role', 'admin');
    }

    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function isSaleManager()
    {
        return $this->role === 'sale_manager';
    }

    public function metas()
    {
        return $this->hasMany(AdminMeta::class);
    }

    public function subsidiaries()
    {
        return $this->belongsToMany(Admin::class, 'admin_sale_manager', 'admin_id', 'sale_manager_id');
    }
}
