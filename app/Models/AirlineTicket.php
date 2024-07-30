<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AirlineTicket extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['airline_id', 'price', 'description'];

    public function airline()
    {
        return $this->belongsTo(Airline::class, 'airline_id');
    }
}
