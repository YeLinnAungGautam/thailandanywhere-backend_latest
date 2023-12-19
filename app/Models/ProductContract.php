<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductContract extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function ownerable(): MorphTo
    {
        return $this->morphTo();
    }
}
