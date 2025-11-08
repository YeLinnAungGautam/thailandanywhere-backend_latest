<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory;
    use SoftDeletes;

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

    public function roomRates()
    {
        return $this->hasMany(PartnerRoomRate::class, 'room_id', 'id');
    }

    public function roitems()
    {
        return $this->belongsToMany(Roitem::class, 'room_roitems');
    }

    public function partnerRoomMetas()
    {
        return $this->hasMany(PartnerRoomMeta::class, 'room_id');
    }

    // ✅ မှန်ကန်တဲ့ grouped method
    public function roitemsGrouped()
    {
        return $this->roitems()
            ->with('rofacility')
            ->get()
            ->groupBy('rofacility.name'); // သို့မဟုတ် 'rofacility.title'
    }
}
