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

    // protected $document_type_validation_rule = 'required|in:passport,booking_confirm_letter,expense_receipt,booking_request_proof,expense_mail_proof,confirmation_letter';

    public static function specificFolderPath(string $type): string
    {
        switch ($type) {
            case 'passport':
                return 'passport/';

            case 'booking_confirm_letter':
                return 'images/';

            case 'confirmation_letter':
                return 'files/';

            case 'expense_receipt':
                return 'images/';

            case 'booking_request_proof':
                return 'images/';

            case 'expense_mail_proof':
                return 'images/';

            default:
                throw new Exception('Invalid document type');
        }
    }
}
