<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Airline extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contract',
        'legal_name',
        'starting_balance',
        'full_description'
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(AirlineTicket::class, 'airline_id', 'id');
    }
}
