<?php

use App\Http\Controllers\API\V2\CarController;
use App\Http\Controllers\API\V2\CityController;
use App\Http\Controllers\API\V2\HotelController;
use App\Http\Controllers\API\V2\PrivateVanTourController;
use App\Http\Controllers\API\V2\RoomController;
use Illuminate\Support\Facades\Route;

Route::group([], function () {
    # Private Van Tour
    Route::get('private-van-tours', [PrivateVanTourController::class, 'index']);
    Route::get('private-van-tours/{private_van_tour_id}', [PrivateVanTourController::class, 'show']);

    # Car
    Route::get('cars', [CarController::class, 'index']);

    # City
    Route::get('cities', [CityController::class, 'index']);

    # Hotel
    Route::get('hotels', [HotelController::class, 'index']);
    Route::get('hotels/{hotel_id}', [HotelController::class, 'show']);

    # Room
    Route::get('rooms', [RoomController::class, 'index']);
    Route::get('rooms/{room_id}', [RoomController::class, 'show']);

    # Entrance Ticket
    
});
