<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InclusivePackage extends Model
{
    use HasFactory ,SoftDeletes;

    protected $fillable = [
        'package_name', 'adults', 'children',
        'start_date', 'end_date', 'nights', 'total_days',
        'day_city_map', 'attractions', 'hotels',
        'van_tours', 'ordered_items', 'descriptions',
        'total_cost_price', 'total_selling_price',
        'status', 'created_by', 'rate_per_person', 'is_clone', 'cloned_from_id'
    ];

    protected $casts = [
        'is_clone'    => 'boolean',
        'day_city_map'=> 'array',
        'descriptions'=> 'array',
    ];

    // ════════════════════════════════
    // Accessors — JSON decode + dayLabel calculate
    // ════════════════════════════════

    public function getAttractionsAttribute($value): array
    {
        $items = is_array($value) ? $value : (json_decode($value, true) ?? []);
        return $this->injectDayLabels($items, 'attraction');
    }

    public function setAttractionsAttribute($value): void
    {
        $this->attributes['attractions'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getVanToursAttribute($value): array
    {
        $items = is_array($value) ? $value : (json_decode($value, true) ?? []);
        return $this->injectDayLabels($items, 'van');
    }

    public function setVanToursAttribute($value): void
    {
        $this->attributes['van_tours'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getHotelsAttribute($value): array
    {
        $items = is_array($value) ? $value : (json_decode($value, true) ?? []);
        return $this->injectDayLabels($items, 'hotel');
    }

    public function setHotelsAttribute($value): void
    {
        $this->attributes['hotels'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getOrderedItemsAttribute($value): array
    {
        $items = is_array($value) ? $value : (json_decode($value, true) ?? []);
        return $this->injectDayLabels($items, 'ordered');
    }

    public function setOrderedItemsAttribute($value): void
    {
        $this->attributes['ordered_items'] = is_array($value) ? json_encode($value) : $value;
    }

    // ════════════════════════════════
    // Core — dayLabel inject logic
    // ════════════════════════════════

    private function injectDayLabels(array $items, string $context): array
    {
        if (empty($items)) return $items;

        $startDate = null;
        if (!empty($this->attributes['start_date'])) {
            $startDate = Carbon::parse($this->attributes['start_date'])->startOfDay();
        }

        foreach ($items as &$item) {
            // ── Van Tour / Attraction — dayNumber မှ dayLabel ──
            if (!empty($item['dayNumber']) && $startDate) {
                $date = $startDate->copy()->addDays((int)$item['dayNumber'] - 1);
                $item['dayLabel'] = $date->format('M j'); // "Mar 3"
            }

            // ── Hotel — checkInDay / checkOutDay မှ labels ──
            if (!empty($item['checkInDay']) && $startDate) {
                $item['checkInLabel'] = $startDate->copy()
                    ->addDays((int)$item['checkInDay'] - 1)
                    ->format('M j');
            }
            if (!empty($item['checkOutDay']) && $startDate) {
                $item['checkOutLabel'] = $startDate->copy()
                    ->addDays((int)$item['checkOutDay'] - 1)
                    ->format('M j');
            }
        }

        return $items;
    }
}
