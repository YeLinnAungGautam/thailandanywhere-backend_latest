<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'product_type',
        'variation_id',
        'quantity',
        'service_date',
        'checkout_date',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
        'service_date' => 'date',
        'checkout_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->morphTo();
    }

    // Helper method to get the variation based on product type
    public function variation()
    {
        switch ($this->product_type) {
            case 'App\Models\Hotel':
                return $this->belongsTo(Room::class, 'variation_id', 'id');
            case 'App\Models\PrivateVanTour':
                return $this->belongsTo(PrivateVanTourCar::class, 'variation_id', 'id');
            case 'App\Models\EntranceTicket':
                return $this->belongsTo(EntranceTicketVariation::class, 'variation_id', 'id');
            default:
                return null;
        }
    }

}
