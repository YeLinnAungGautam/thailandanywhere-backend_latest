<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Partner extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

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

    public function hotels()
    {
        return $this->morphedByMany(Hotel::class, 'productable', 'partner_has_products');
    }

    public function privateVanTours()
    {
        return $this->morphedByMany(PrivateVanTour::class, 'productable', 'partner_has_products');
    }

    public function entranceTickets()
    {
        return $this->morphedByMany(EntranceTicket::class, 'productable', 'partner_has_products');
    }

    public function groupTours()
    {
        return $this->morphedByMany(GroupTour::class, 'productable', 'partner_has_products');
    }

    public function inclusiveProducts()
    {
        return $this->morphedByMany(Inclusive::class, 'productable', 'partner_has_products');
    }
}
