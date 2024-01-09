<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // phpinfo();
    abort(503);
});


Route::get('slack-test', function () {
    $message = 'Slack notification: ' . now()->format('Y-m-d H:i a');

    Log::info($message);

    return 'Logged successfully';
});
