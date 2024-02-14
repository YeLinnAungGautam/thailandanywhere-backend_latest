<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AirlineController;
use App\Http\Controllers\Admin\AirlineTicketController;
use App\Http\Controllers\Admin\AirportPickupController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CarController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DestinationController;
use App\Http\Controllers\Admin\EntranceTicketController;
use App\Http\Controllers\Admin\EntranceTicketVariationController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\GroupTourController;
use App\Http\Controllers\Admin\InclusiveController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PrivateVanTourController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductSubCategoryController;
use App\Http\Controllers\Admin\ProductTagController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\HotelReportController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\ReservationExportController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/bookings/{id}/receipt', [BookingController::class, 'printReceipt']);
Route::get('/reservations/{id}/receipt', [ReservationController::class, 'printReservation']);
Route::get('/hotel-reservation/{id}/receipt', [ReservationController::class, 'printReservationHotel']);
Route::get('/vantour-reservation/{id}/receipt', [ReservationController::class, 'printReservationVantour']);

# Reservation Export
Route::get('reservations/report/export', [ReservationExportController::class, 'exportReservationReport']);

Route::get('/customer-sale', [ReportController::class, 'getCustomerSale']);

Route::get('/super', function () {
    return 'this is super admin only';
})->middleware(['auth:sanctum', 'abilities:*']);


Route::middleware(['auth:sanctum', 'abilities:admin'])->group(function () {

    Route::get('/sales-report', [ReportController::class, 'salesAmount']);
    Route::get('/sales-count', [ReportController::class, 'salesCount']);
    Route::get('/bookings-count', [ReportController::class, 'bookingsCount']);
    Route::get('/reservations-count', [ReportController::class, 'reservationsCount']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('reports/hotels', HotelReportController::class);

    Route::get('/get-reports/{id}', [ReportController::class, 'getSelectData']);
    Route::get('/get-each-user-report', [ReportController::class, 'getEachUserSaleCount']);
    Route::get('/general-reports/{date}', [ReportController::class, 'generalSaleReport']);

    Route::apiResource('admins', AdminController::class);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('current-sale-rank', [AdminController::class, 'getCurrentSaleRank']);

    Route::get('categories-list', [CategoryController::class, 'getCategoryList']);
    Route::get('tags-list', [TagController::class, 'getTagList']);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('posts', PostController::class);

    Route::apiResource('product-categories', ProductCategoryController::class);
    Route::apiResource('product-sub-categories', ProductSubCategoryController::class);
    Route::apiResource('destinations', DestinationController::class);
    Route::apiResource('cities', CityController::class);
    Route::apiResource('cars', CarController::class);
    Route::apiResource('product-tags', ProductTagController::class);
    Route::apiResource('private-van-tours', PrivateVanTourController::class);
    Route::apiResource('group-tours', GroupTourController::class);
    Route::apiResource('entrance-tickets-variations', EntranceTicketVariationController::class);
    Route::apiResource('entrance-tickets', EntranceTicketController::class);
    Route::apiResource('airport-pickups', AirportPickupController::class);
    Route::apiResource('inclusive', InclusiveController::class);

    # Facility
    Route::apiResource('facilities', FacilityController::class);

    # Customer
    Route::apiResource('customers', CustomerController::class);
    Route::get('customers/{id}/sales', [CustomerController::class, 'getSales']);

    Route::apiResource('bookings', BookingController::class);

    # Reservation
    Route::put('reservations/info/{id}', [ReservationController::class, 'updateInfo']);
    Route::apiResource('reservations', ReservationController::class);
    Route::get('reservations/{id}/copy', [ReservationController::class, 'copyDetail']);
    Route::get('calendar/reservations', [CalendarController::class, 'index']);
    Route::post('reservations/{booking_item}/send-notify-email', [ReservationController::class, 'sendNotifyEmail']);

    # Hotel
    Route::apiResource('hotels', HotelController::class);
    Route::delete('hotels/{hotel}/images/{hotel_image}', [HotelController::class, 'deleteImage']);

    # Room
    Route::apiResource('rooms', RoomController::class);
    Route::delete('rooms/{room}/images/{room_image}', [RoomController::class, 'deleteImage']);

    # Restaurant
    Route::apiResource('restaurants', RestaurantController::class);
    Route::delete('restaurants/{restaurant}/images/{product_image}', [RestaurantController::class, 'deleteImage']);

    # Meal
    Route::apiResource('meals', MealController::class);
    Route::delete('meals/{meal}/images/{product_image}', [MealController::class, 'deleteImage']);

    # Driver
    Route::apiResource('drivers', DriverController::class);

    # Supplier
    Route::apiResource('suppliers', SupplierController::class);

    Route::apiResource('airlines', AirlineController::class);
    Route::apiResource('airline-tickets', AirlineTicketController::class);

    Route::delete('booking-receipt/{id}', [BookingController::class, 'deleteReceipt']);
    Route::delete('reservation-receipt/{id}', [ReservationController::class, 'deleteReceipt']);
    Route::delete('confirmation-receipt/{id}', [ReservationController::class, 'deleteConfirmationReceipt']);
    Route::delete('customer-passport/{id}', [ReservationController::class, 'deleteCustomerPassport']);
});
