<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingItemAmendment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_item_id',
        'amend_history',
        'amend_request',
        'amend_mail_sent',
        'amend_approve',
        'amend_status',
    ];
    protected $casts = [
        'amend_history' => 'array',
        'amend_request' => 'boolean',
        'amend_mail_sent' => 'boolean',
        'amend_approve' => 'boolean',
        'amend_status' => 'string',
    ];

    /**
     * The booking item that this amendment belongs to.
     */
    public function bookingItem()
    {
        return $this->belongsTo(BookingItem::class);
    }
}
