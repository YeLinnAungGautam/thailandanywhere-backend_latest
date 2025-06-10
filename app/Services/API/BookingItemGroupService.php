<?php
namespace App\Services\API;

use App\Models\EntranceTicket;
use App\Models\Hotel;
use App\Models\PrivateVanTour;

class BookingItemGroupService
{
    public function getModelBy(string $product_type)
    {
        switch ($product_type) {
            case 'attraction':
                return EntranceTicket::class;
            case 'hotel':
                return Hotel::class;
            case 'private_van_tour':
                return PrivateVanTour::class;
            default:
                throw new \InvalidArgumentException("Invalid product type: {$product_type}");
        }
    }
}
