<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'checkin_date' => 'date',
        'checkout_date' => 'date',
        'service_date' => 'date',
        'individual_pricing' => 'json',
        'room_rates' => 'json',
        'incomplete_allotment' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->morphTo();
    }

    public function variation()
    {
        return $this->belongsTo(EntranceTicketVariation::class, 'variation_id');
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
