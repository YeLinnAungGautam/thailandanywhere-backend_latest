<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Booking extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'balance_due_date' => 'date:Y-m-d',
        // ... other casts
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->invoice_number = $model->generateInvoiceNumber();
            $model->crm_id = $model->generateCrmID();
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function pastUser()
    {
        return $this->belongsTo(Admin::class, 'past_user_id');
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);

        // return $this->hasMany(BookingItem::class)
        //     ->whereNotIn('product_type', [Airline::class, AirportPickup::class]);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'booking_id');
    }

    public function receipts()
    {
        return $this->hasMany(BookingReceipt::class);
    }

    public function bookingItemGroups()
    {
        return $this->hasMany(BookingItemGroup::class);
    }

    public function generateInvoiceNumber()
    {
        $number = date('YmdHis');

        // ensure unique
        while ($this->invoiceNumberExists($number)) {
            $number = str_pad((int) $number + 1, 12, '0', STR_PAD_LEFT);
        }

        return $number;
    }

    public function invoiceNumberExists($number)
    {
        return static::where('invoice_number', $number)->exists();
    }

    public function generateCrmID()
    {
        $user = Auth::user();

        // Ensure the first letter of each word is capitalized
        $name = ucwords(strtolower($user->name));

        // Split the name into words
        $words = explode(' ', $name);

        // Get the first letter of the first word
        $firstInitial = $words[0][0];

        // Get the first letter of the last word
        $lastInitial = $words[count($words) - 1][0];

        // If the first letters of both words are the same, take the second letter of the second word
        if ($firstInitial == $lastInitial && isset($words[count($words) - 1][1])) {
            $lastInitial = $words[count($words) - 1][1];
        }

        $fullName = strtoupper($firstInitial . $lastInitial);

        // Count previous bookings for the user
        $previousBookingsCount = static::where('created_by', $user->id)->count();

        // Construct the booking ID
        $bookingId = $fullName . '-' . str_pad($previousBookingsCount + 1, 4, '0', STR_PAD_LEFT);

        while (static::where('crm_id', $bookingId)->exists()) {
            ++$previousBookingsCount;

            $bookingId = $fullName . '-' . str_pad($previousBookingsCount, 4, '0', STR_PAD_LEFT);
        }


        return $bookingId;
    }

    public function getAcsrSubTotalAttribute()
    {
        return $this->sub_total + $this->exclude_amount;
    }

    public function getAcsrGrandTotalAttribute()
    {
        return $this->grand_total + $this->exclude_amount;
    }

    public function saleCases()
    {
        return $this->hasMany(CaseTable::class, 'related_id')
            ->where('case_type', 'sale');
    }
}
