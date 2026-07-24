<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Memory extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingItem()
    {
        return $this->belongsTo(BookingItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(MemoryImage::class)->orderBy('sort_order');
    }

    // The product this memory is about (Hotel, EntranceTicket, PrivateVanTour...)
    public function getProductAttribute()
    {
        return $this->bookingItem?->product;
    }

    public function likes()
    {
        return $this->hasMany(MemoryLike::class);
    }

    // Reaction counts grouped by type, e.g. ['like' => 3, 'love' => 1]
    public function getReactionCountsAttribute()
    {
        return $this->likes()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray() ?: (object) []; // force {} instead of [] when empty
    }

    // The current authenticated user's reaction on this memory, if any
    public function getUserReactionAttribute()
    {
        if (!Auth::check()) {
            return null;
        }

        return $this->likes()
            ->where('user_id', Auth::id())
            ->value('type');
    }
}
