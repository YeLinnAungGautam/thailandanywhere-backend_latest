<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hotel Service Mail Settings
    |--------------------------------------------------------------------------
    |
    | These credentials are used by the UsesHotelServiceMail trait to send
    | emails via the dedicated Hotel Service mailer.
    |
    */

    'driver' => env('HOTEL_SERVICE_MAIL_DRIVER', 'smtp'),
    'host' => env('HOTEL_SERVICE_MAIL_HOST', 'smtp.gmail.com'),
    'port' => env('HOTEL_SERVICE_MAIL_PORT', 587),
    'username' => env('HOTEL_SERVICE_MAIL_USERNAME'),
    'password' => env('HOTEL_SERVICE_MAIL_PASSWORD'),
    'encryption' => env('HOTEL_SERVICE_MAIL_ENCRYPTION', 'tls'),

    'from' => [
        'address' => env('HOTEL_SERVICE_MAIL_FROM_ADDRESS'),
        'name' => env('HOTEL_SERVICE_MAIL_FROM_NAME', 'Thailand Anywhere'),
    ],

];
