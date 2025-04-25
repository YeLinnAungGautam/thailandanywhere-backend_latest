<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupTour extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function images()
    {
        return $this->hasMany(GroupTourImage::class, 'group_tour_id', 'id');
    }

    public function destinations()
    {
        return $this->belongsToMany(Destination::class, 'group_tour_destinations', 'group_tour_id', 'destination_id');
    }

    public function tags()
    {
        return $this->belongsToMany(ProductTag::class, 'group_tour_tags', 'group_tour_id', 'product_tag_id');
    }

    public function cities()
    {
        return $this->belongsToMany(City::class, 'group_tour_cities', 'group_tour_id', 'city_id');
    }

    public function partners()
    {
        return $this->morphToMany(Partner::class, 'productable', 'partner_has_products');
    }
}
