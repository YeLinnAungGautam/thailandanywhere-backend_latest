<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CategoryController;

Route::post('/login', [AuthController::class,'login']);
Route::post('/register', [AuthController::class,'register']);

Route::get('categories', [CategoryController::class,'getList']);
Route::get('posts', [PostController::class,'getPost']);
Route::get('popular-posts', [PostController::class,'getPopularPost']);
Route::get('recent-posts', [PostController::class,'getRecentPost']);
Route::get('feature-posts', [PostController::class,'getFeaturePost']);
Route::get('posts/{slug}', [PostController::class,'getDetail']);

Route::middleware(['auth:sanctum', 'abilities:user'])->group(function () {
    Route::get('/me', [AuthController::class,'me']);
    Route::post('/logout', [AuthController::class,'logout']);
    Route::post('posts/{id}/comments', [CommentController::class,'addComment']);
    Route::post('posts/{id}/react', [CommentController::class,'toggleReact']);
    Route::delete('comments/{id}', [CommentController::class,'deleteComment']);
});


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
