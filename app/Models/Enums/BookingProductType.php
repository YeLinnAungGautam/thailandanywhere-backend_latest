<?php
namespace App\Models\Enums;

abstract class BookingProductType
{
    const PRIVATE_VAN_TOUR = 'Private Van Tour';
    const GROUP_TOUR = 'Group Tour';
    const ENTRANCE_TICKET = 'Entrance Ticket';
    const AIRPORT_PICKUP = 'Airport Pickup';
    const HOTEL = 'Hotel';
    const ARI_LINE = 'Airline';

    public static $values = [
        'App\Models\PrivateVanTour' => self::PRIVATE_VAN_TOUR,
        'App\Models\GroupTour' => self::GROUP_TOUR,
        'App\Models\EntranceTicket' => self::ENTRANCE_TICKET,
        'App\Models\AirportPickup' => self::AIRPORT_PICKUP,
        'App\Models\Hotel' => self::HOTEL,
        'App\Models\Airline' => self::ARI_LINE,
    ];
}
