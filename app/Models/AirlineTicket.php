<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AirlineTicket extends Model
{
    use HasFactory;

    protected $fillable = ['airline_id', 'price', 'description'];

    public function airline()
    {
        return $this->belongsTo(Airline::class, 'airline_id');
    }
}
