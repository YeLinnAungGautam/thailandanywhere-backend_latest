<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    abort(503);
});

// Route::get('test', function () {
//     Mail::raw('This is a test email.', function ($message) {
//         $message->to('taryarlin0088@gmail.com')
//             ->subject('Test Email');
//     });

//     return 'success';
// });
