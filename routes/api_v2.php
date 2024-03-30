<?php

use App\Http\Controllers\API\V2\CarController;
use App\Http\Controllers\API\V2\CityController;
use App\Http\Controllers\API\V2\EntranceTicketController;
use App\Http\Controllers\API\V2\EntranceTicketVariationController;
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
    Route::get('entrance-tickets', [EntranceTicketController::class, 'index']);
    Route::get('entrance-tickets/{entrance_ticket_id}', [EntranceTicketController::class, 'show']);

    # Entrance Ticket Variation
    Route::get('entrance-ticket-variations', [EntranceTicketVariationController::class, 'index']);
    Route::get('entrance-ticket-variations/{entrance_ticket_variation_id}', [EntranceTicketVariationController::class, 'show']);

    # Group Tour
    // Route::get('group-tours', [EntranceTicketController::class, 'index']);
    // Route::get('group-tours/{entrance_ticket_id}', [EntranceTicketController::class, 'show']);
});
