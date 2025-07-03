<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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
        'is_active' => 'boolean',
    ];

    /**
     * Check if user email is verified
     *
     * @return bool
     */
    public function isVerified()
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Check if user account is active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->is_active === true;
    }

    /**
     * Check if user can access the system (both verified and active)
     *
     * @return bool
     */
    public function canAccessSystem()
    {
        return $this->isVerified() && $this->isActive();
    }

    public function oauthProviders()
    {
        return $this->hasMany(OAuthProvider::class);
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->unique_key = Str::uuid();
        });
    }
}
