<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rofacility extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function roitems()
    {
        return $this->hasMany(Roitem::class, 'rofacility_id');
    }
}
