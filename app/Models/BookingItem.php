<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pickup_time',
        'days',
        'customer_attachment',
        'expense_amount',
        'hotel_id',
        'room_id',
        'crm_id',
        'route_plan',
        'pickup_location',
        'dropoff_location',
        'booking_id',
        'special_request',
        'car_id',
        'product_type',
        'product_id',
        'service_date',
        'quantity',
        'duration',
        'selling_price',
        'comment',
        'reservation_status',
        'receipt_image',
        'cost_price',
        'room_number',
        'payment_status',
        'payment_method',
        'confirmation_letter',
        'exchange_rate',
        'variation_id',
        'checkin_date',
        'checkout_date',
        'amount',
        'is_inclusive',
        'slip_code',
        'is_associated'
    ];

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
}
