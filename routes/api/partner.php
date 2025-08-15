<?php

use App\Http\Controllers\API\Partner\AuthPartnerController;
use App\Http\Controllers\API\Partner\ForgotPasswordController;
use App\Http\Controllers\API\Partner\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthPartnerController::class, 'loginPartner']);

Route::get('/test', function() {
    return response()->json(['message' => 'test']);
});

Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('reset-password', [ResetPasswordController::class, 'reset']);

Route::middleware(['auth:sanctum', 'abilities:partner'])->group(function () {
    Route::get('me', [AuthPartnerController::class, 'loginUser']);

    Route::post('logout', [AuthPartnerController::class, 'logout']);
});
