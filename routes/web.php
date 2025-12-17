<?php

use App\Http\Controllers\GmailController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    abort(503);
});

// Route::get('test', function () {
//     Mail::raw('Dear KO THI HA, This is the actual system notification.Please try clicking “Reply” or “Reply All” to test it.But please wait for Ko Ye Lin’s call.', function ($message) {
//         $message->to('kumarmyanmars@gmail.com')
//             ->bcc(['taryarlin0088@gmail.com'])
//             ->replyTo(['dev.taryarlin@gmail.com', 'ceo@thanywhere.com', 'davidgautam.1234@gmail.com'])
//             ->subject('Test Email');
//     });

//     return 'success';
// });

Route::get('/google/connect', [GmailController::class, 'redirectToGoogle'])->name('google.connect');
Route::get('/gmail/oauth/callback', [GmailController::class, 'handleGoogleCallback'])->name('google.callback');

Route::get('/gmail/inbox', [GmailController::class, 'inbox'])->name('gmail.inbox');
Route::get('/gmail/thread/{threadId}', [GmailController::class, 'thread'])->name('gmail.thread');
Route::post('/gmail/reply', [GmailController::class, 'reply'])->name('gmail.reply');
