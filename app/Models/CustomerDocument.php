<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDocument extends Model
{
    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];
}
