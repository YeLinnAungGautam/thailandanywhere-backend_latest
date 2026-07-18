<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Promo extends Model
{
    use HasFactory;

    protected $primaryKey = 'promo_id';

    public const PRODUCT_TYPES = [
        'hotel'           => 'App\Models\Hotel',
        'entrance_ticket' => 'App\Models\EntranceTicket',
        'vantour'         => 'App\Models\PrivateVanTour',
        'inclusive'       => 'App\Models\GroupTour',
        'airline'         => 'App\Models\Airline',
        'airport_pickup'  => 'App\Models\AirportPickup',
    ];

    protected $fillable = [
        'promo_name', 'promo_des', 'promo_code', 'promo_type', 'promo_amount',
        'promo_count', 'promo_used_count', 'promo_active',
        'promo_start_date', 'promo_end_date', 'promo_applies_to', 'applicable_products','image'
    ];

    protected $casts = [
        'promo_active'        => 'boolean',
        'promo_start_date'    => 'datetime',
        'promo_end_date'      => 'datetime',
        'promo_amount'        => 'decimal:2',
        'applicable_products' => 'array',
    ];

    public function usages()
    {
        return $this->hasMany(PromoUsage::class, 'promo_id', 'promo_id');
    }

    public function isWithinDateRange(): bool
    {
        $now = Carbon::now();

        if ($this->promo_start_date && $now->lt($this->promo_start_date)) {
            return false;
        }

        return $now->lte($this->promo_end_date);
    }

    public function hasUsesLeft(): bool
    {
        return $this->promo_used_count < $this->promo_count;
    }

    public function isValid(): bool
    {
        return $this->promo_active && $this->isWithinDateRange() && $this->hasUsesLeft();
    }

    /**
     * Is this specific booking item's product type/id eligible for the promo?
     */
    public function isApplicableToItem(string $productTypeClass, ?int $productId): bool
    {
        if ($this->promo_applies_to === 'all') {
            return true;
        }

        $friendlyKey = array_search($productTypeClass, self::PRODUCT_TYPES, true);

        if (! $friendlyKey || ! isset($this->applicable_products[$friendlyKey])) {
            return false;
        }

        $rule = $this->applicable_products[$friendlyKey];

        if ($rule === 'all') {
            return true;
        }

        return is_array($rule) && in_array($productId, $rule, false);
    }

    /**
     * Calculate the discount for one booking item's amount.
     */
    public function calculateDiscount(float $itemAmount): float
    {
        if ($itemAmount <= 0) {
            return 0;
        }

        if ($this->promo_type === 'percent') {
            return round($itemAmount * ((float) $this->promo_amount / 100), 2);
        }

        return min((float) $this->promo_amount, $itemAmount);
    }
}
