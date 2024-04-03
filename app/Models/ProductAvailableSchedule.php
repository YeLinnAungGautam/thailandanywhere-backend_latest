<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAvailableSchedule extends Model
{
    protected $guarded = [];

    public function ownerable()
    {
        return $this->morphTo();
    }

    public function variable()
    {
        return $this->morphTo();
    }
}
