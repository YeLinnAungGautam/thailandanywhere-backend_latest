<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntranceTicket extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'full_description',
        'provider',
        'cancellation_policy_id',
        'cover_image',
        'place',
        'legal_name',
        'bank_name',
        'payment_method',
        'bank_account_number',
        'account_name'
    ];

    public function images()
    {
        return $this->hasMany(EntranceTicketImage::class, 'entrance_ticket_id', 'id');
    }

    public function variations()
    {
        return $this->hasMany(EntranceTicketVariation::class, 'entrance_ticket_id', 'id');
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

    public function contracts(): HasMany
    {
        return $this->hasMany(EntranceTicketContract::class, 'entrance_ticket_id');
    }

    public function activities()
    {
        return $this->belongsToMany(AttractionActivity::class, 'activity_entrance_ticket');
    }

    public function bookingItems()
    {
        return $this->morphMany(BookingItem::class, 'product');
    }
}
