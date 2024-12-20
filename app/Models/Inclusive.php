<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inclusive extends Model
{
    // protected $fillable = ['name', 'description', 'sku_code', 'price', 'agent_price', 'day', 'night', 'cover_image'];

    protected $guarded = [];

    public function InclusiveDetails()
    {
        return $this->hasMany(InclusiveDetail::class);
    }

    public function groupTours()
    {

        return $this->hasMany(InclusiveGroupTour::class);
    }

    public function entranceTickets()
    {

        return $this->hasMany(InclusiveEntranceTicket::class);
    }

    public function airportPickups()
    {

        return $this->hasMany(InclusiveAirportPickup::class);
    }

    public function privateVanTours()
    {
        return $this->hasMany(InclusivePrivateVanTour::class);
    }

    public function airlineTickets()
    {

        return $this->hasMany(InclusiveAirlineTicket::class);
    }

    public function hotels()
    {

        return $this->hasMany(InclusiveHotel::class);
    }

    public function images()
    {
        return $this->hasMany(InclusiveImage::class)->whereNull('type');
    }

    public function overviewFiles()
    {
        return $this->hasMany(InclusiveImage::class)->where('type', 'overview_pdf');
    }
}
