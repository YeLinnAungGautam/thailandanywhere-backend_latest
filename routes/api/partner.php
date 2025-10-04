<?php

use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\API\Partner\AuthPartnerController;
use App\Http\Controllers\API\Partner\CashImageController;
use App\Http\Controllers\API\Partner\DashboardController;
use App\Http\Controllers\API\Partner\DefaultRoomRateController;
use App\Http\Controllers\API\Partner\ForgotPasswordController;
use App\Http\Controllers\API\Partner\HotelPartnerController;
use App\Http\Controllers\API\Partner\ReservationController;
use App\Http\Controllers\API\Partner\ResetPasswordController;

use App\Http\Controllers\API\Partner\RoomController;
use App\Http\Controllers\API\Partner\RoomRateController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthPartnerController::class, 'loginPartner']);

Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('reset-password', [ResetPasswordController::class, 'reset']);

Route::middleware(['auth:sanctum', 'abilities:partner'])->group(function () {
    Route::get('me', [AuthPartnerController::class, 'loginUser']);

    Route::put('update/{id}', [AuthPartnerController::class, 'updateProfile']);

    Route::post('change/{id}/password', [AuthPartnerController::class, 'changePassword']);

    Route::post('logout', [AuthPartnerController::class, 'logout']);

    // reservation part
    Route::get('booking_item_groups', [ReservationController::class, 'index']);
    Route::get('booking_item_groups/{id}', [ReservationController::class, 'detail']);
    Route::get('booking_item_groups/{id}/customer_documents', [ReservationController::class, 'getCustomerDocuments']);
    Route::post('booking_item_groups/{id}/customer_documents', [ReservationController::class, 'store']);
    Route::put('booking_item_groups/{id}/customer_documents/{customer_document_id}', [ReservationController::class, 'update']);
    Route::delete('booking_item_groups/{id}/customer_documents/{customer_document_id}', [ReservationController::class, 'delete']);

    // booking items
    Route::get('booking_items', [ReservationController::class, 'getBookingItems']);

    // Get monthly sales graph data
    Route::post('monthly-sales', [DashboardController::class, 'getMonthlySalesGraph']);

    // Cash Image
    Route::get('cash-images', [CashImageController::class, 'index']);
    Route::get('cash-images/{id}', [CashImageController::class, 'show']);

    // hotel
    Route::get('hotels/{hotel}', [HotelPartnerController::class, 'show']);
    Route::put('hotels/{hotel}', [HotelPartnerController::class, 'update']);

    // hotel images
    Route::delete('hotel/{hotel}/image/{hotel_image}', [HotelPartnerController::class, 'deleteImage']);
    Route::post('hotel/{hotel}/image', [HotelPartnerController::class, 'addImage']);
    Route::post('hotel/{hotel}/image/{hotel_image}', [HotelPartnerController::class, 'editImage']);

    // hotel contracts
    Route::delete('hotel/{id}/contract/{cid}', [HotelPartnerController::class, 'deleteContract']);
    Route::post('hotel/contract/{id}', [HotelPartnerController::class, 'addContract']);

    // hotel slug
    Route::post('hotels/{hotel}/slug', [HotelPartnerController::class, 'addSlug']);

    Route::apiResource('facilities', FacilityController::class);

    # Room
    Route::apiResource('rooms', RoomController::class);
    Route::delete('rooms/{room}/images/{room_image}', [RoomController::class, 'deleteImage']);

    # Default Room Rates
    Route::apiResource('hotels/{hotel}/rooms/{room}/default-rates', DefaultRoomRateController::class)->only(['store', 'destroy']);

    # Room Rates
    Route::apiResource('hotels/{hotel}/rooms/{room}/rates', RoomRateController::class);
});
