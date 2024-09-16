<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function entranceTickets()
    {
        return $this->belongsToMany(EntranceTicket::class, 'entrance_ticket_categories', 'category_id', 'entrance_ticket_id');
    }
}
