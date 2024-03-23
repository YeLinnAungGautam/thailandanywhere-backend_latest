<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AirlineController;
use App\Http\Controllers\Admin\AirlineTicketController;
use App\Http\Controllers\Admin\AirportPickupController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CarBookingController;
use App\Http\Controllers\Admin\CarController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
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
use App\Http\Controllers\AirlineExportImportController;
use App\Http\Controllers\AirlineTicketExportImportController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\DriverInfoController;
use App\Http\Controllers\EntranceTicketExportImportController;
use App\Http\Controllers\EntranceTicketVariationExportImportController;
use App\Http\Controllers\GroupTourExportImportController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\HotelExportImportController;
use App\Http\Controllers\HotelReportController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\MealExportImportController;
use App\Http\Controllers\PrivateVanTourExportImportController;
use App\Http\Controllers\ReservationExportController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\RestaurantExportImportController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomExportImportController;
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

    # Dashboard
    Route::get('/sales-report', [ReportController::class, 'salesAmount']);
    Route::get('/sales-count', [ReportController::class, 'salesCount']);
    Route::get('/bookings-count', [ReportController::class, 'bookingsCount']);
    Route::get('/reservations-count', [ReportController::class, 'reservationsCount']);

    Route::get('report-by-channel', [DashboardController::class, 'reportByChannel']);
    Route::get('report-by-payment-method', [DashboardController::class, 'reportByPaymentMethod']);
    Route::get('report-by-payment-status', [DashboardController::class, 'reportByPaymentStatus']);
    Route::get('report-by-payment-and-product', [DashboardController::class, 'reportByPaymentAndProduct']);

    Route::get('sales-by-agent', [DashboardController::class, 'salesByAgentReport']);
    Route::get('unpaid-bookings', [DashboardController::class, 'getUnpaidBooking']);
    Route::get('sale-counts', [DashboardController::class, 'getSaleCounts']);
    Route::get('top-selling-products', [DashboardController::class, 'getTopSellingProduct']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('reports/hotels', HotelReportController::class);

    Route::get('get-reports/{id}', [ReportController::class, 'getSelectData']);
    Route::get('get-each-user-report', [ReportController::class, 'getEachUserSaleCount']);
    Route::get('general-reports/{date}', [ReportController::class, 'generalSaleReport']);

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
    Route::apiResource('cities', CityController::class);
    Route::apiResource('cars', CarController::class);
    Route::apiResource('product-tags', ProductTagController::class);

    # Group Tour
    Route::apiResource('group-tours', GroupTourController::class);
    Route::get('group-tours/export/csv', [GroupTourExportImportController::class, 'export']);
    Route::post('group-tours/import/csv', [GroupTourExportImportController::class, 'import']);

    # Entrance Ticket
    Route::apiResource('entrance-tickets', EntranceTicketController::class);
    Route::get('entrance-tickets/export/csv', [EntranceTicketExportImportController::class, 'export']);
    Route::post('entrance-tickets/import/csv', [EntranceTicketExportImportController::class, 'import']);

    # Entrance Ticket Variation
    Route::apiResource('entrance-tickets-variations', EntranceTicketVariationController::class);
    Route::get('entrance-tickets-variations/export/csv', [EntranceTicketVariationExportImportController::class, 'export']);
    Route::post('entrance-tickets-variations/import/csv', [EntranceTicketVariationExportImportController::class, 'import']);

    # Airport Pickup
    Route::apiResource('airport-pickups', AirportPickupController::class);

    # Inclusive
    Route::apiResource('inclusive', InclusiveController::class);

    # Destination
    Route::apiResource('destinations', DestinationController::class);
    Route::delete('destinations/{destination_id}/images/{product_image_id}', [DestinationController::class, 'deleteImage']);
    Route::post('destinations/{destination_id}/images', [DestinationController::class, 'uploadImage']);

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

    # Private Vantour
    Route::apiResource('private-van-tours', PrivateVanTourController::class);
    Route::get('private-van-tours/export/csv', [PrivateVanTourExportImportController::class, 'export']);
    Route::post('private-van-tours/import/csv', [PrivateVanTourExportImportController::class, 'import']);

    # Hotel
    Route::apiResource('hotels', HotelController::class);
    Route::delete('hotels/{hotel}/images/{hotel_image}', [HotelController::class, 'deleteImage']);
    Route::get('hotels/export/csv', [HotelExportImportController::class, 'export']);
    Route::post('hotels/import/csv', [HotelExportImportController::class, 'import']);

    # Room
    Route::apiResource('rooms', RoomController::class);
    Route::delete('rooms/{room}/images/{room_image}', [RoomController::class, 'deleteImage']);
    Route::get('rooms/export/csv', [RoomExportImportController::class, 'export']);
    Route::post('rooms/import/csv', [RoomExportImportController::class, 'import']);

    # Restaurant
    Route::apiResource('restaurants', RestaurantController::class);
    Route::delete('restaurants/{restaurant}/images/{product_image}', [RestaurantController::class, 'deleteImage']);
    Route::get('restaurants/export/csv', [RestaurantExportImportController::class, 'export']);
    Route::post('restaurants/import/csv', [RestaurantExportImportController::class, 'import']);

    # Meal
    Route::apiResource('meals', MealController::class);
    Route::delete('meals/{meal}/images/{product_image}', [MealController::class, 'deleteImage']);
    Route::get('meals/export/csv', [MealExportImportController::class, 'export']);
    Route::post('meals/import/csv', [MealExportImportController::class, 'import']);

    # Driver
    Route::apiResource('drivers', DriverController::class);

    # Driver Info
    Route::apiResource('drivers/{driver_id}/infos', DriverInfoController::class);

    # Supplier
    Route::apiResource('suppliers', SupplierController::class);

    # Airline
    Route::apiResource('airlines', AirlineController::class);
    Route::get('airlines/export/csv', [AirlineExportImportController::class, 'export']);
    Route::post('airlines/import/csv', [AirlineExportImportController::class, 'import']);

    # Airline Ticket
    Route::apiResource('airline-tickets', AirlineTicketController::class);
    Route::get('airline-tickets/export/csv', [AirlineTicketExportImportController::class, 'export']);
    Route::post('airline-tickets/import/csv', [AirlineTicketExportImportController::class, 'import']);

    # Car Booking
    Route::get('car-bookings', [CarBookingController::class, 'index']);
    Route::get('car-bookings/{booking_item_id}/edit', [CarBookingController::class, 'edit']);
    Route::post('car-bookings/{booking_item_id}', [CarBookingController::class, 'update']);
    Route::get('car-bookings/summary', [CarBookingController::class, 'getSummary']);

    Route::delete('booking-receipt/{id}', [BookingController::class, 'deleteReceipt']);
    Route::delete('reservation-receipt/{id}', [ReservationController::class, 'deleteReceipt']);
    Route::delete('confirmation-receipt/{id}', [ReservationController::class, 'deleteConfirmationReceipt']);
    Route::delete('customer-passport/{id}', [ReservationController::class, 'deleteCustomerPassport']);
});
