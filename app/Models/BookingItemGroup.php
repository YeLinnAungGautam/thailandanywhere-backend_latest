<?php

namespace App\Models;

use App\Traits\HasCashImages;
use Illuminate\Database\Eloquent\Model;

class BookingItemGroup extends Model
{
    use HasCashImages;

    protected $guarded = [];

    protected $casts = [
        'passport_info' => 'array',
    ];

    public function bookingItems()
    {
        return $this->hasMany(BookingItem::class, 'group_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function customerDocuments()
    {
        return $this->hasMany(CustomerDocument::class, 'booking_item_group_id');
    }

    public function passports()
    {
        return $this->hasMany(CustomerDocument::class, 'booking_item_group_id')->where('type', 'passport');
    }

    public function cashImages()
    {
        return $this->morphMany(CashImage::class, 'relatable');
    }

    public function taxReceipts()
    {
        return $this->belongsToMany(TaxReceipt::class, 'tax_receipt_groups', 'booking_item_group_id', 'tax_receipt_id')
            ->withTimestamps()->withPivot('id');
    }
}
