<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTicketMessage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function emailTicket()
    {
        return $this->belongsTo(EmailTicket::class, 'ticket_id');
    }
}
