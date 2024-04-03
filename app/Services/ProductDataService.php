<?php
namespace App\Services;

use App\Models\EntranceTicket;
use App\Models\EntranceTicketVariation;
use App\Models\Hotel;
use App\Models\Room;
use Exception;

class ProductDataService
{
    public static function getProductModelByName(string $product_type)
    {
        switch ($product_type) {
            case 'hotel':
                return Hotel::class;

                break;

            case 'entrance_ticket':
                return EntranceTicket::class;

                break;

            default:
                throw new Exception('Invalid product type');

                break;
        }
    }

    public static function getVariationByProductType(string $product_type)
    {
        switch ($product_type) {
            case 'hotel':
                return Room::class;

                break;

            case 'entrance_ticket':
                return EntranceTicketVariation::class;

                break;

            default:
                throw new Exception('Invalid product variation');

                break;
        }
    }
}
