<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Destination extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function privateVanTours()
    {
        return $this->belongsToMany(PrivateVanTour::class, 'private_van_tour_destinations', 'destination_id', 'private_van_tour_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function images(): MorphMany
    {
        return $this->morphMany(ProductImage::class, 'ownerable');
    }
}
