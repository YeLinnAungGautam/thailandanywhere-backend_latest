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

    protected $appends = ['variation'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->morphTo();
    }

    // Or define specific accessors for each variation type
    public function getVariationAttribute()
    {
        if (!$this->variation_id) {
            return null;
        }

        switch ($this->product_type) {
            case 'App\Models\Hotel':
                return Room::find($this->variation_id);
            case 'App\Models\PrivateVanTour':
                return PrivateVanTourCar::find($this->variation_id);
            case 'App\Models\EntranceTicket':
                return EntranceTicketVariation::find($this->variation_id);
            default:
                return null;
        }
    }

}
