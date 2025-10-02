<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerRoomMeta extends Model
{
    protected $guarded = [];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
