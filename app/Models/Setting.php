<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    const HOTEL_DISCOUNT = 'hotel_discount';
    const ENTRANCE_TICKET_DISCOUNT = 'entrance_ticket_discount';
}
