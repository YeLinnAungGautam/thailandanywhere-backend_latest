<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $guarded = [];

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class);
    }
}
