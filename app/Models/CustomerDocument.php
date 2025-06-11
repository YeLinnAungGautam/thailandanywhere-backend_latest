<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;

class CustomerDocument extends Model
{
    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public static function specificFolderPath(string $type): string
    {
        switch ($type) {
            case 'passport':
                return 'passport/';

            case 'tax_slip':
                return 'images/';

            case 'paid_slip':
                return 'images/';

            case 'receipt_image':
                return 'images/';

            case 'booking_confirm_letter':
                return 'images/';

            case 'confirmation_letter':
                return 'files/';

            case 'car_photo':
                return 'images/';

            default:
                throw new Exception('Invalid document type');
        }
    }
}
