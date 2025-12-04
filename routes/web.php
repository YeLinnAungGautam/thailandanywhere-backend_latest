<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    abort(503);
});

Route::get('test', function () {
    Mail::raw('This is a test email.', function ($message) {
        $message->to('davidgautam.1234@gmail.com')
            ->cc(['gg.haak0007@gmail.com'])
            ->bcc(['taryarlin0088@gmail.com'])
            ->replyTo(['dev.taryarlin@gmail.com', 'homnathgautam23@gmail.com'])
            ->subject('Test Email');
    });

    return 'success';
});
