<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'date',
        'sender',
        'receiver',
        'amount',
        'interact_bank',
        'currency',
        'internal_transfer',
        'data_verify',
        'bank_verify',
        'relatable_type',
        'relatable_id',
        'relatables'
    ];

    protected $casts = [
        'date' => 'datetime', // Changed to datetime for date and time
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'internal_transfer' => 'boolean',
        'data_verify' => 'boolean',
        'bank_verify' => 'boolean',
        'relatables' => 'array'
    ];

    // Polymorphic relationship
    public function relatable()
    {
        return $this->morphTo();
    }

    public function bookings()
    {
        return $this->belongsToMany(
            Booking::class,
            'cash_image_bookings', // pivot table name
            'cash_image_id',       // foreign key for cash_image
            'booking_id'           // foreign key for booking
        )->withPivot('deposit', 'notes', 'id')
            ->withTimestamps();
    }

    // Helper method to get all related bookings (both polymorphic and pivot)
    public function getAllBookings()
    {
        $bookings = collect();

        // Add polymorphic booking if exists
        if ($this->relatable_type === 'App\Models\Booking' && $this->relatable) {
            $bookings->push($this->relatable);
        }

        // Add pivot table bookings
        $bookings = $bookings->merge($this->bookings);

        return $bookings->unique('id');
    }

    public function cashBookings()
    {
        return $this->morphedByMany(Booking::class, 'imageable', 'cash_imageables')
            ->withPivot(['type', 'deposit', 'notes'])
            ->withTimestamps();
    }

    public function cashBooks()
    {
        return $this->morphedByMany(CashBook::class, 'imageable', 'cash_imageables')
            ->withPivot(['type', 'deposit', 'notes'])
            ->withTimestamps();
    }

    public function cashBookingItemGroups()
    {
        return $this->morphedByMany(BookingItemGroup::class, 'imageable', 'cash_imageables')
            ->withPivot(['type', 'deposit', 'notes'])
            ->withTimestamps();
    }

    // Relationship with internal transfers
    public function internalTransfers()
    {
        return $this->belongsToMany(
            InternalTransfer::class,
            'internal_transfer_cash_images',
            'cash_image_id',
            'internal_transfer_id'
        )
        ->withTimestamps()
        ->withPivot('direction');
    }

    // Transfers where this is source
    public function transfersFrom()
    {
        return $this->internalTransfers()->wherePivot('direction', 'from');
    }

    // Transfers where this is destination
    public function transfersTo()
    {
        return $this->internalTransfers()->wherePivot('direction', 'to');
    }
}
