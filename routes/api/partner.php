<?php
use App\Http\Controllers\API\Partner\AuthController;
use App\Http\Controllers\API\Partner\ForgotPasswordController;
use App\Http\Controllers\API\Partner\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('reset-password', [ResetPasswordController::class, 'reset']);

Route::middleware(['auth:sanctum', 'abilities:partner'])->group(function () {
    Route::get('user', [AuthController::class, 'loginUser']);

    Route::post('logout', [AuthController::class, 'logout']);
});
