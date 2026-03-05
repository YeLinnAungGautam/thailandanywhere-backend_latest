<?php

namespace App\Models;

use App\Traits\HasCashImages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Booking extends Model
{
    use HasFactory, HasCashImages;

    protected $guarded = [];

    protected $casts = [
        'balance_due_date' => 'date:Y-m-d',
        // ... other casts
    ];

    // public static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($model) {
    //         $model->invoice_number = $model->generateInvoiceNumber();
    //         $model->crm_id = $model->generateCrmID();
    //     });
    // }

    // ✅ Single boot() — combines creating + deleting hooks
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->invoice_number = $model->generateInvoiceNumber();
            $model->crm_id = $model->generateCrmID();
        });

        static::deleting(function (Booking $booking) {

            // ── Eager-load everything into memory BEFORE cascade can fire ──
            $booking->loadMissing(['items.product', 'customer']);

            $now      = now()->toDateTimeString();
            $userId   = Auth::id();
            $userName = Auth::user()?->name ?? 'System';

            foreach ($booking->items as $bookingItem) {

                // ── Mirror exact buildItemSnapshot() from AmendmentController ──
                $productName = null;
                try {
                    $productName = $bookingItem->product?->name ?? null;
                } catch (\Exception $e) {}

                // ── Decode all JSON string fields before storing in snapshot ──
                $individualPricing = $bookingItem->individual_pricing;
                if (is_string($individualPricing)) {
                    $individualPricing = json_decode($individualPricing, true) ?? null;
                }

                $variationSnapshot = $bookingItem->variation_snapshot;
                if (is_string($variationSnapshot)) {
                    $variationSnapshot = json_decode($variationSnapshot, true) ?? null;
                }

                $productSnapshot = $bookingItem->product_snapshot;
                if (is_string($productSnapshot)) {
                    $productSnapshot = json_decode($productSnapshot, true) ?? null;
                }

                $priceSnapshot = $bookingItem->price_snapshot;
                if (is_string($priceSnapshot)) {
                    $priceSnapshot = json_decode($priceSnapshot, true) ?? null;
                }

                $itemSnapshot = [
                    // ── Core item fields ────────────────────────────────────
                    'id'                  => $bookingItem->id,
                    'booking_id'          => $bookingItem->booking_id,
                    'crm_id'              => $bookingItem->crm_id,
                    'product_type'        => $bookingItem->product_type,
                    'product_id'          => $bookingItem->product_id,
                    'service_date'        => $bookingItem->service_date,
                    'checkout_date'       => $bookingItem->checkout_date,
                    'checkin_date'        => $bookingItem->checkin_date,
                    'quantity'            => $bookingItem->quantity,
                    'selling_price'       => $bookingItem->selling_price,
                    'cost_price'          => $bookingItem->cost_price,
                    'amount'              => $bookingItem->amount,
                    'total_cost_price'    => $bookingItem->total_cost_price,
                    'discount'            => $bookingItem->discount,
                    'days'                => $bookingItem->days,
                    'individual_pricing'  => $individualPricing,
                    'item_name'           => $bookingItem->item_name ?? null,
                    'comment'             => $bookingItem->comment ?? null,
                    'special_request'     => $bookingItem->special_request ?? null,
                    'pickup_location'     => $bookingItem->pickup_location ?? null,
                    'pickup_time'         => $bookingItem->pickup_time ?? null,
                    'route_plan'          => $bookingItem->route_plan ?? null,
                    'cancellation'        => $bookingItem->cancellation ?? null,
                    // ── Decoded snapshots (parsed from JSON strings) ─────────
                    'product_snapshot'    => $productSnapshot,
                    'variation_snapshot'  => $variationSnapshot,
                    'price_snapshot'      => $priceSnapshot,
                    // ── Resolved relations ───────────────────────────────────
                    'product'             => $productName ? ['id' => $bookingItem->product_id, 'name' => $productName] : null,
                    'customer_info'       => $booking->customer ? ['id' => $booking->customer->id, 'name' => $booking->customer->name] : null,
                    'booking'             => [
                        'id'           => $booking->id,
                        'crm_id'       => $booking->crm_id,
                        'booking_date' => $booking->booking_date,
                        'is_inclusive' => $booking->is_inclusive,
                    ],
                    // ── Snapshot metadata ────────────────────────────────────
                    'snapshotted_at' => $now,
                ];

                // ✅ Pass plain arrays — mirrors approveAmendment exactly:
                //    $amendment->item_snapshot = $this->buildItemSnapshot(...)
                //    The BookingItemAmendment model cast handles JSON serialization.
                //    booking_item_id = null because MySQL CASCADE already deleted the items.
                //    Original ID is preserved in item_snapshot['id'] and amend_history.
                BookingItemAmendment::create([
                    'booking_item_id' => null,
                    'amend_history'   => [
                        [
                            'timestamp'                => $now,
                            'changes'                  => ['delete' => true],
                            'previous_values'          => [],
                            'user_id'                  => $userId,
                            'user_name'                => $userName,
                            'approved_by'              => $userId,
                            'approved_at'              => $now,
                            'deleted_reason'           => 'Booking deleted',
                            'original_booking_item_id' => $bookingItem->id,
                        ],
                    ],
                    'item_snapshot'   => $itemSnapshot,
                    'amend_request'   => true,
                    'amend_mail_sent' => false,
                    'amend_approve'   => true,
                    'amend_status'    => 'completed',
                ]);
            }
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
    public function cashImages()
    {
        return $this->morphMany(CashImage::class, 'relatable');
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

    // New: Many-to-many relationship
    public function cashImagesPivot()
    {
        return $this->belongsToMany(
            CashImage::class,
            'cash_image_bookings', // pivot table name
            'booking_id',       // foreign key for cash_image
            'cash_image_id'           // foreign key for booking
        )->withPivot('deposit', 'notes', 'id')
            ->withTimestamps();
    }

    // Helper method to get all cash images
    public function getAllCashImages()
    {
        return $this->cashImages->merge($this->cashImagesPivot)->unique('id');
    }


}
