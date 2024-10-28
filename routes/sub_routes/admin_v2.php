<?php

use App\Http\Controllers\Admin\CustomerInformationController;
use App\Http\Controllers\Admin\RoomPeriodController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function () {
    # Reservation - Booking Item
    Route::post('booking-items/{bookingItem}/customer-information', [CustomerInformationController::class, 'store']);


});
