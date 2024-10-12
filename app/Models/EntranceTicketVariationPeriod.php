<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntranceTicketVariationPeriod extends Model
{
    protected $guarded = [];

    public function EntranceTicketVariation()
    {
        return $this->belongsTo(EntranceTicketVariation::class);
    }
}
