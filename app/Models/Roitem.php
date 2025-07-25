<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roitem extends Model
{
    use HasFactory;

    protected $guarded = [];

    // ✅ မှန်ကန်တဲ့ relationship
    public function rofacility()
    {
        return $this->belongsTo(Rofacility::class, 'rofacility_id');
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'room_roitems');
    }
}
