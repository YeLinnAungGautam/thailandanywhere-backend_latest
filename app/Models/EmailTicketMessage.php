<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTicketMessage extends Model
{
    protected $guarded = [];

    public function emailTicket()
    {
        return $this->belongsTo(EmailTicket::class);
    }
}
