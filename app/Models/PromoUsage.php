<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoUsage extends Model
{
    protected $fillable = [
        'promo_id',
        'booking_item_id',
        'customer_id',
        'order_item_id',
        'discount_applied',
    ];

    public function promo()
    {
        return $this->belongsTo(Promo::class, 'promo_id', 'promo_id');
    }

    public function bookingItem()
    {
        return $this->belongsTo(BookingItem::class);
    }
     public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }
}
