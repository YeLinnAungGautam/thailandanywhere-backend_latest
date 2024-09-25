<?php
use App\Http\Controllers\API\Supplier\CarBookingController;
use App\Http\Controllers\API\Supplier\LoginController;
use App\Http\Controllers\API\Supplier\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('supplier')->group(function () {
    Route::post('login', [LoginController::class, 'login']);

    Route::middleware(['auth:sanctum', 'abilities:supplier'])->group(function () {
        Route::post('logout', [LoginController::class, 'logout']);

        Route::get('profile', [ProfileController::class, 'profile']);
        Route::put('profile', [ProfileController::class, 'updateProfile']);
        Route::post('change-password', [ProfileController::class, 'changePassword']);

        Route::get('car-bookings', [CarBookingController::class, 'index']);
        Route::get('car-bookings/{id}', [CarBookingController::class, 'show']);
    });
});
