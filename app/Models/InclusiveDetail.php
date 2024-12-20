<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InclusiveDetail extends Model
{
    protected $guarded = [];

    public function inclusive()
    {
        return $this->belongsTo(Inclusive::class);
    }

    public function cities()
    {
        return $this->belongsToMany(City::class, 'city_inclusive_info');
    }

    public function destinations()
    {
        return $this->belongsToMany(Destination::class, 'destination_inclusive_info');
    }
}
