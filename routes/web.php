<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    abort(503);
});

Route::get('test', function () {
    Mail::raw('This is a test email.', function ($message) {
        $message->to('davidgautam.1234@gmail.com')
            ->replyTo(['dev.taryarlin@gmail.com', 'ceo@thanywhere.com', 'kumarmyanmars@gmail.com'])
            ->subject('Test Email');
    });

    return 'success';
});
