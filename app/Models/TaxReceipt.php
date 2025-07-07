<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_type',
        'product_id',
        'company_legal_name',
        'receipt_date',
        'service_start_date',
        'service_end_date',
        'receipt_image',
        'additional_codes',
        'total_tax_withold',
        'total_tax_amount',
        'total_after_tax',
        'invoice_number',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'service_start_date' => 'date',
        'service_end_date' => 'date',
        'additional_codes' => 'array',
        'total_tax_withold' => 'decimal:2',
        'total_tax_amount' => 'decimal:2',
        'total_after_tax' => 'decimal:2',
    ];


    /**
     * Get the product that owns the tax receipt (polymorphic relationship).
     */
    public function product()
    {
        return $this->morphTo();
    }

    /**
     * Get reservations associated with this tax receipt.
     * Many-to-many relationship with reservations.
     */
    public function groups()
    {
        return $this->belongsToMany(BookingItemGroup::class, 'tax_receipt_groups', 'tax_receipt_id', 'booking_item_group_id')
                    ->withTimestamps()->withPivot('id');
    }
}
