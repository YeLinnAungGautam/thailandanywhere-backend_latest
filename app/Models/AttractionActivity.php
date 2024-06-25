<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttractionActivity extends Model
{
    protected $guarded = [];

    public function entranceTickets()
    {
        return $this->belongsToMany(EntranceTicket::class, 'activity_entrance_ticket');
    }
}
