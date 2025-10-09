<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InternalTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'exchange_rate',
        'notes',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
    ];

    // Cash images associated as "from" (source)
    public function cashImagesFrom()
    {
        return $this->belongsToMany(
            CashImage::class,
            'internal_transfer_cash_images',
            'internal_transfer_id',
            'cash_image_id'
        )
        ->wherePivot('direction', 'from')
        ->withTimestamps()
        ->withPivot('direction');
    }

    // Cash images associated as "to" (destination)
    public function cashImagesTo()
    {
        return $this->belongsToMany(
            CashImage::class,
            'internal_transfer_cash_images',
            'internal_transfer_id',
            'cash_image_id'
        )
        ->wherePivot('direction', 'to')
        ->withTimestamps()
        ->withPivot('direction');
    }

    // All cash images (both from and to)
    public function cashImages()
    {
        return $this->belongsToMany(
            CashImage::class,
            'internal_transfer_cash_images',
            'internal_transfer_id',
            'cash_image_id'
        )
        ->withTimestamps()
        ->withPivot('direction');
    }

    // Get total amount from
    public function getTotalAmountFromAttribute()
    {
        return $this->cashImagesFrom->sum('amount');
    }

    // Get total amount to
    public function getTotalAmountToAttribute()
    {
        return $this->cashImagesTo->sum('amount');
    }

    // Get currency from (from first cash image)
    public function getCurrencyFromAttribute()
    {
        return $this->cashImagesFrom->first()?->currency;
    }

    // Get currency to (from first cash image)
    public function getCurrencyToAttribute()
    {
        return $this->cashImagesTo->first()?->currency;
    }
}
