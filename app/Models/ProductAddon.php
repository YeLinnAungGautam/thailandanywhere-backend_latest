<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAddon extends Model
{
    protected $guarded = [];

    public function product()
    {
        return $this->morphTo();
    }
}
