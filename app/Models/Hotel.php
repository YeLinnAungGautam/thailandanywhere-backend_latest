<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = ['legal_name', 'contract_due', 'name', 'city_id', 'place', 'payment_method', 'bank_name', 'bank_account_number', 'account_name'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'hotel_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(HotelContract::class, 'hotel_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(HotelImage::class, 'hotel_id');
    }

}
