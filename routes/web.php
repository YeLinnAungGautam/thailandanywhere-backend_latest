<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    info('Testing slack');
    abort(503);
});
