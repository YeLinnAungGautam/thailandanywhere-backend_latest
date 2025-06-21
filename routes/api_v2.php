<?php

use App\Http\Controllers\Admin\BookingReceiptController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\API\LoginController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\V2\AirlineController;
use App\Http\Controllers\API\V2\AirlineTicketController;
use App\Http\Controllers\API\V2\AttractionActivityController;
use App\Http\Controllers\API\V2\BookingController;
use App\Http\Controllers\API\V2\CarController;
use App\Http\Controllers\API\V2\CartController;
use App\Http\Controllers\API\V2\CityController;
use App\Http\Controllers\API\V2\DestinationController;
use App\Http\Controllers\API\V2\EntranceTicketController;
use App\Http\Controllers\API\V2\EntranceTicketVariationController;
use App\Http\Controllers\API\V2\FacilityController;
use App\Http\Controllers\API\V2\GroupTourController;
use App\Http\Controllers\API\V2\HotelCityController;
use App\Http\Controllers\API\V2\HotelController;
use App\Http\Controllers\API\V2\InclusiveController;
use App\Http\Controllers\API\V2\MealController;
use App\Http\Controllers\API\V2\PrivateVanTourController;
use App\Http\Controllers\API\V2\ReservationController;
use App\Http\Controllers\API\V2\RestaurantController;
use App\Http\Controllers\API\V2\RoomController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::group([], function () {
    # Private Van Tour
    Route::get('private-van-tours', [PrivateVanTourController::class, 'index']);
    Route::get('private-van-tours/{private_van_tour_id}', [PrivateVanTourController::class, 'show']);

    # Car
    Route::get('cars', [CarController::class, 'index']);

    # Destination
    Route::get('destinations', [DestinationController::class, 'index']);
    Route::get('destinations/{destination}', [DestinationController::class, 'show']);
    Route::get('destinations/{id}/related-tours', [DestinationController::class, 'getRelatedTours']);

    # City
    Route::get('cities', [CityController::class, 'index']);

    # Hotel
    Route::get('hotels', [HotelController::class, 'index']);
    Route::get('hotels/{hotel_id}', [HotelController::class, 'show']);

    # Hotel Cities
    Route::get('hotel-cities', HotelCityController::class);

    # Hotel Facilities
    Route::get('facilities', [FacilityController::class, 'index']);

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
    Route::get('group-tours', [GroupTourController::class, 'index']);
    Route::get('group-tours/{group_tour_id}', [GroupTourController::class, 'show']);

    # Airline
    Route::get('airlines', [AirlineController::class, 'index']);
    Route::get('airlines/{airline_id}', [AirlineController::class, 'show']);

    # Airline Ticket
    Route::get('airline-tickets', [AirlineTicketController::class, 'index']);
    Route::get('airline-tickets/{airline_ticket_id}', [AirlineTicketController::class, 'show']);

    # Restaurant
    Route::get('restaurants', [RestaurantController::class, 'index']);
    Route::get('restaurants/{restaurant_id}', [RestaurantController::class, 'show']);

    # Meal
    Route::get('meals', [MealController::class, 'index']);
    Route::get('meals/{meal_id}', [MealController::class, 'show']);

    # Attraction Activities
    Route::get('attraction-activities', [AttractionActivityController::class, 'index']);

    # Inclusive
    Route::get('inclusives', [InclusiveController::class, 'index']);
    Route::get('inclusives/{id}', [InclusiveController::class, 'show']);

    # Register
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('/verify-email', [RegisterController::class, 'verifyEmail']);
    Route::post('/resend-verification-email', [RegisterController::class, 'resendVerificationEmail']);


    # Login
    Route::post('login', [LoginController::class, 'login']);
    // Route::get('all/receipts',[BookingReceiptController::class, 'getall']);

    # Reservation Information
    Route::get('reservation-information/{id}', [ReservationController::class, 'reservationInformation']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('logout', [LoginController::class, 'logout']);

        # Bookings
        Route::get('bookings', [BookingController::class, 'index']);
        Route::get('bookings/{id}', [BookingController::class, 'show']);
        Route::post('bookings/{id}', [BookingController::class, 'store']);
        Route::get('bookings/{id}/check-user', [BookingController::class, 'checkBookingHasUser']);

        # Cart
        Route::get('carts', [CartController::class, 'index']);
        Route::post('carts', [CartController::class, 'store']);
        Route::get('carts/{id}', [CartController::class, 'show']);
        Route::put('carts/{cart}', [CartController::class, 'update']);
        Route::delete('carts/{cart}', [CartController::class, 'destroy']);
        Route::delete('carts/clear', [CartController::class, 'clear']);

        # Order
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
        Route::post('checkout', [OrderController::class, 'checkout']);
        Route::delete('orders/{id}', [OrderController::class, 'cancelOrder']);
        Route::post('orders/{id}/change-to-booking', [OrderAdminController::class, 'changeOrderToBooking']);
        Route::put('orders/{id}/update', [OrderController::class, 'update']);
    });
});
