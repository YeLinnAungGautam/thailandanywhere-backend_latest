<?php

use App\Http\Controllers\API\Frontend\CityController;
use App\Http\Controllers\API\Frontend\DestinationController;
use App\Http\Controllers\API\Frontend\HotelController;
use App\Http\Controllers\API\Frontend\PageController;
use App\Http\Controllers\API\Frontend\PrivateVantourController;
use App\Http\Controllers\API\Frontend\ProductCategoryController;
use App\Http\Controllers\API\SocialiteLoginController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('categories', [CategoryController::class, 'getList']);
Route::get('posts', [PostController::class, 'getPost']);
Route::get('popular-posts', [PostController::class, 'getPopularPost']);
Route::get('recent-posts', [PostController::class, 'getRecentPost']);
Route::get('feature-posts', [PostController::class, 'getFeaturePost']);
Route::get('posts/{slug}', [PostController::class, 'getDetail']);

Route::middleware(['auth:sanctum', 'abilities:user'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('posts/{id}/comments', [CommentController::class, 'addComment']);
    Route::post('posts/{id}/react', [CommentController::class, 'toggleReact']);
    Route::delete('comments/{id}', [CommentController::class, 'deleteComment']);
});


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('profile', [UserProfileController::class, 'profile']);
// });

# Frontend URI
Route::group([
    'prefix' => 'v1/customer-portal',
], function () {
    Route::get('/', [PageController::class, 'index']);
    Route::get('cities/{id}', [PageController::class, 'show']);

    # City
    Route::get('cities', [CityController::class, 'index']);

    # Destination
    Route::apiResource('destinations', DestinationController::class)->only('index');

    # Category
    Route::get('product-categories', [ProductCategoryController::class, 'index']);

    # Private Van Tour
    Route::apiResource('private-van-tours', PrivateVantourController::class)->only('show');
    Route::get('private-van-tours/{id}/related-tours', [PrivateVantourController::class, 'getRelatedTours']);

    # Hotel
    Route::apiResource('hotels', HotelController::class)->only('show');
    Route::get('hotels/{id}/related-hotels', [HotelController::class, 'getRelatedHotels']);
});

# Socialite Login
Route::post('oauth/{driver}/redirect', [SocialiteLoginController::class, 'redirect']);
Route::get('oauth/{driver}/callback', [SocialiteLoginController::class, 'callback'])->name('oauth.callback');
