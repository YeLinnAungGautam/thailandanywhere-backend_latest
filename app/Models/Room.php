<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'cost' => 'integer',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class, 'hotel_id');
    }

    public function images()
    {
        return $this->hasMany(RoomImage::class);
    }

    public function periods()
    {
        return $this->hasMany(RoomPeriod::class, 'room_id', 'id');
    }
}
