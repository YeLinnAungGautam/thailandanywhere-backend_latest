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

    public function groupTour()
    {
        return $this->belongsTo(GroupTour::class);
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

    public function scopePrivateVanTour($query)
    {
        return $query->where('product_type', PrivateVanTour::class);
    }

    public function getAcsrProductTypeNameAttribute()
    {
        switch ($this->product_type) {
            case 'App\Models\Hotel':
                return 'Hotel & Room';

                break;

            case 'App\Models\PrivateVanTour':
                return 'Private Van Tour';

                break;

            case 'App\Models\Airline':
                return 'Airline';

                break;

            case 'App\Models\AirportPickup':
                return 'Airport Pickup';

                break;

            case 'App\Models\EntranceTicket':
                return 'Entrance Ticket';

                break;

            case 'App\Models\GroupTour':
                return 'Group Tour';

                break;

            case 'App\Models\Inclusive':
                return 'Inclusive';

                break;

            default:
                return 'Unknown';

                break;
        }
    }

    public function getAcsrVariationNameAttribute()
    {
        if($this->product_type === 'App\Models\PrivateVanTour') {
            return $this->car->name ?? '-';
        }

        if($this->product_type === 'App\Models\EntranceTicket') {
            return $this->variation->name ?? '-';
        }

        if($this->product_type === 'App\Models\Hotel') {
            return $this->room->name ?? '-';
        }

        if($this->product_type === 'App\Models\Airline') {
            return $this->ticket->price ?? '-';
        }

        if($this->product_type === 'App\Models\GroupTour') {
            return $this->groupTour->name ?? '-';
        }

        return '-';
    }
}
