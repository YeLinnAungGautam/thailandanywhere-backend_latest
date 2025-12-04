<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    abort(503);
});

Route::get('test', function () {
    Mail::raw('Dear KO THI HA, This is the actual system notification.Please try clicking “Reply” or “Reply All” to test it.But please wait for Ko Ye Lin’s call.', function ($message) {
        $message->to('kumarmyanmars@gmail.com')
            ->bcc(['taryarlin0088@gmail.com'])
            ->replyTo(['dev.taryarlin@gmail.com', 'ceo@thanywhere.com', 'davidgautam.1234@gmail.com'])
            ->subject('Test Email');
    });

    return 'success';
});
