<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTicket extends Model
{
    protected $guarded = [];

    public function messages()
    {
        return $this->hasMany(EmailTicketMessage::class);
    }
}
