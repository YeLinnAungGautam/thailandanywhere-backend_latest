<?php

namespace App\Models;

use App\Models\City;
use App\Models\ProductTag;
use App\Models\EntranceVariation;
use App\Models\EntranceTicketImage;
use App\Models\EntranceTicketVariation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EntranceTicket extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'provider', 'cancellation_policy_id', 'cover_image'];

    public function images()
    {
        return $this->hasMany(EntranceTicketImage::class, 'entrance_ticket_id', 'id');
    }

    public function variations()
    {
        return $this->belongsToMany(EntranceTicketVariation::class, 'entrance_variations', 'entrance_ticket_id', 'variation_id');
    }

    public function tags()
    {
        return $this->belongsToMany(ProductTag::class, 'entrance_ticket_tags', 'entrance_ticket_id', 'product_tag_id');
    }

    public function cities()
    {
        return $this->belongsToMany(City::class, 'entrance_ticket_cities', 'entrance_ticket_id', 'city_id');
    }

    public function categories()
    {
        return $this->belongsToMany(ProductCategory::class, 'entrance_ticket_categories', 'entrance_ticket_id', 'category_id');
    }
}
