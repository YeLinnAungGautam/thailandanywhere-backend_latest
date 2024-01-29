<?php

namespace App\Models;

use App\Services\BookingItemDataService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $hidden = [
        'laravel_through_key'
    ];

    public function product()
    {
        return $this->morphTo();
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function variation()
    {
        return $this->belongsTo(EntranceTicketVariation::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function ticket()
    {
        return $this->belongsTo(AirlineTicket::class, 'ticket_id', 'id');
    }

    public function reservationInfo()
    {
        return $this->hasOne(ReservationInfo::class, 'booking_item_id');
    }

    public function reservationCarInfo()
    {
        return $this->hasOne(ReservationCarInfo::class, 'booking_item_id');
    }

    public function reservationSupplierInfo()
    {
        return $this->hasOne(ReservationSupplierInfo::class, 'booking_item_id');
    }

    public function reservationBookingConfirmLetter()
    {
        return $this->hasMany(ReservationBookingConfirmLetter::class, 'booking_item_id');
    }

    public function reservationReceiptImage()
    {
        return $this->hasMany(ReservationExpenseReceipt::class, 'booking_item_id');
    }

    public function reservationCustomerPassport()
    {
        return $this->hasMany(ReservationCustomerPassport::class, 'booking_item_id');
    }

    public function reservationPaidSlip()
    {
        return $this->hasMany(ReservationPaidSlip::class, 'booking_item_id');
    }

    public function associatedCustomer()
    {
        return $this->hasMany(ReservationAssociatedCustomer::class, 'booking_item_id');
    }

    protected function calcSalePrice(): Attribute
    {
        return Attribute::make(get: fn () => (new BookingItemDataService($this))->getSalePrice());
    }
}
