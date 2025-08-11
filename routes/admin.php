<?php

use App\Http\Controllers\Accountance\AccountanceGenerateController;
use App\Http\Controllers\Accountance\CashBookController;
use App\Http\Controllers\Accountance\CashImageBookingController;
use App\Http\Controllers\Accountance\CashImageController;
use App\Http\Controllers\Accountance\CashStructureController;
use App\Http\Controllers\Accountance\VatCalculationController;
use App\Http\Controllers\Admin\AccountClassController;
use App\Http\Controllers\Admin\AccountHeadController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminMetaController;
use App\Http\Controllers\Admin\AirlineController;
use App\Http\Controllers\Admin\AirlineTicketController;
use App\Http\Controllers\Admin\AirportPickupController;
use App\Http\Controllers\Admin\AmendmentController;
use App\Http\Controllers\Admin\AttractionActivityController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingConfirmLetterController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BookingItemGroupController;
use App\Http\Controllers\Admin\BookingItemVerifyController;
use App\Http\Controllers\Admin\BookingReceiptController;
use App\Http\Controllers\Admin\CarBookingController;
use App\Http\Controllers\Admin\CarController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChartOfAccountController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerDocumentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DestinationController;
use App\Http\Controllers\Admin\EntranceTicketController;
use App\Http\Controllers\Admin\EntranceTicketVariationController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\GroupTourController;
use App\Http\Controllers\Admin\HotelCategoryController;
use App\Http\Controllers\Admin\InclusiveController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\PlaceController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PrivateVanTourController;
use App\Http\Controllers\Admin\ProductAddonController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductReportController;
use App\Http\Controllers\Admin\ProductSubCategoryController;
use App\Http\Controllers\Admin\ProductTagController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReservationBookingRequestController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\ReservationCustomerPassportController;
use App\Http\Controllers\Admin\ReservationExpenseMailController;
use App\Http\Controllers\Admin\ReservationExpenseReceiptController;
use App\Http\Controllers\Admin\ReservationHotelController;
use App\Http\Controllers\Admin\ReservationListExportController;
use App\Http\Controllers\Admin\ReservationPaidSlipController;
use App\Http\Controllers\Admin\RoomPeriodController;
use App\Http\Controllers\Admin\SaleManagerController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\TaxReceiptController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AirlineExportImportController;
use App\Http\Controllers\AirlineTicketExportImportController;
use App\Http\Controllers\BalanceDueOverController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\DriverInfoController;
use App\Http\Controllers\EntranceTicketExportImportController;
use App\Http\Controllers\EntranceTicketVariationExportImportController;
use App\Http\Controllers\File\FileController;
use App\Http\Controllers\GroupTourExportImportController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\HotelExportImportController;
use App\Http\Controllers\HotelReportController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\MealExportImportController;
use App\Http\Controllers\PrivateVanTourExportImportController;
use App\Http\Controllers\ProductAvailableScheduleController;
use App\Http\Controllers\ReservationExportController;
use App\Http\Controllers\ReservationTransactionController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\RestaurantExportImportController;
use App\Http\Controllers\RofacilityController;
use App\Http\Controllers\RoitemController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomExportImportController;
use App\Http\Controllers\SupplierController;
use App\Models\Booking;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/bookings/{id}/receipt', [BookingController::class, 'printReceipt']);
// for cedit
Route::get('/bookings/{id}/credit', [BookingController::class, 'printCredit']);
Route::get('/reservations/{id}/receipt', [ReservationController::class, 'printReservation']);
Route::get('/hotel-reservation/{id}/receipt', [ReservationController::class, 'printReservationHotel']);
Route::get('/vantour-reservation/{id}/receipt', [ReservationController::class, 'printReservationVantour']);

Route::get('/download-pdf/{id}', [InclusiveController::class, 'downloadPdf']);

Route::get('product-sale-count-report', [ReportController::class, 'getSaleCountReport']);

# Reservation Export
Route::get('reservations/report/export', [ReservationExportController::class, 'exportReservationReport']);

Route::get('/customer-sale', [ReportController::class, 'getCustomerSale']);

Route::get('/print/cash-image', [CashImageController::class, 'printCashImage']);
Route::get('/print/cash-parchase-image', [CashImageController::class, 'printCashParchaseImage']);
Route::get('/pdf-status/{jobId}', [CashImageController::class, 'checkPdfStatus']);
Route::get('/pdf-batch-status/{jobId}', [CashImageController::class, 'checkPdfBatchStatus']);


Route::get('/super', function () {
    return 'this is super admin only';
})->middleware(['auth:sanctum', 'abilities:*']);


Route::middleware(['auth:sanctum', 'abilities:admin'])->group(function () {
    # Dashboard
    Route::get('/sales-report', [ReportController::class, 'salesAmount']);
    Route::get('/sales-count', [ReportController::class, 'salesCount']);
    Route::get('/bookings-count', [ReportController::class, 'bookingsCount']);
    Route::get('/reservations-count', [ReportController::class, 'reservationsCount']);

    Route::post('/generate-Accounting-Pdf', [AccountanceGenerateController::class, 'generateAccountingPdf']);

    Route::get('report-by-channel', [DashboardController::class, 'reportByChannel']);
    Route::get('report-by-payment-method', [DashboardController::class, 'reportByPaymentMethod']);
    Route::get('report-by-payment-status', [DashboardController::class, 'reportByPaymentStatus']);
    Route::get('report-by-payment-and-product', [DashboardController::class, 'reportByPaymentAndProduct']);
    // Route::get('')

    Route::get('/reports/dashboard-sales', [ProductReportController::class, 'getDailyProductSalesDB']);
    Route::get('/product-sales-report', [ProductReportController::class, 'getTopProductsByType']);
    Route::get('/product-sales-report-by-month', [ProductReportController::class, 'getMonthlyTopProductsByType']);

    Route::get('sales-by-agent', [DashboardController::class, 'salesByAgentReport']);
    Route::get('unpaid-bookings', [DashboardController::class, 'getUnpaidBooking']);
    Route::get('sale-counts', [DashboardController::class, 'getSaleCounts']);
    Route::get('top-selling-products', [DashboardController::class, 'getTopSellingProduct']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('reports/hotels', HotelReportController::class);

    Route::get('get-reports/{id}', [ReportController::class, 'getSelectData']);
    Route::get('get-each-user-report', [ReportController::class, 'getEachUserSaleCount']);
    Route::get('general-reports/{date}', [ReportController::class, 'generalSaleReport']);
    Route::get('general-cash-image-reports/{date}', [ReportController::class, 'generalCashImageReport']);

    Route::apiResource('admins', AdminController::class);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout/all', [AuthController::class, 'logoutAll']);
    Route::get('current-sale-rank', [AdminController::class, 'getCurrentSaleRank']);

    Route::get('categories-list', [CategoryController::class, 'getCategoryList']);
    Route::get('tags-list', [TagController::class, 'getTagList']);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('posts', PostController::class);

    # Product Category
    Route::apiResource('product-categories', ProductCategoryController::class);
    Route::post('product-categories/import/csv', [ProductCategoryController::class, 'import']);

    Route::apiResource('product-sub-categories', ProductSubCategoryController::class);

    # City
    Route::apiResource('cities', CityController::class);
    Route::post('cities/import/csv', [CityController::class, 'import']);

    Route::apiResource('cars', CarController::class);
    Route::apiResource('product-tags', ProductTagController::class);

    # Group Tour
    Route::apiResource('group-tours', GroupTourController::class);

    Route::patch('group-tours/{id}/restore', [GroupTourController::class, 'restore']);
    Route::delete('group-tours/{id}/force', [GroupTourController::class, 'hardDelete']);

    Route::get('group-tours/export/csv', [GroupTourExportImportController::class, 'export']);
    Route::post('group-tours/import/csv', [GroupTourExportImportController::class, 'import']);

    # Entrance Ticket
    Route::apiResource('entrance-tickets', EntranceTicketController::class);

    Route::delete('entrance-tickets/{image_id}/delete', [EntranceTicketController::class, 'deleteImage']);

    Route::patch('entrance-tickets/{id}/restore', [EntranceTicketController::class, 'restore']);
    Route::delete('entrance-tickets/{id}/force', [EntranceTicketController::class, 'hardDelete']);

    Route::delete('entrance-tickets/{entrance_ticket}/contracts/{entrance_ticket_contract}', [EntranceTicketController::class, 'deleteContract']);
    Route::get('entrance-tickets/export/csv', [EntranceTicketExportImportController::class, 'export']);
    Route::post('entrance-tickets/import/csv', [EntranceTicketExportImportController::class, 'import']);

    # Entrance Ticket Variation
    Route::apiResource('entrance-tickets-variations', EntranceTicketVariationController::class);

    Route::patch('entrance-tickets-variations/{id}/restore', [EntranceTicketVariationController::class, 'restore']);
    Route::delete('entrance-tickets-variations/{id}/force', [EntranceTicketVariationController::class, 'hardDelete']);

    Route::delete('entrance-tickets-variations/{entrance_ticket_variation_id}/images/{product_image_id}', [EntranceTicketVariationController::class, 'deleteImage']);
    Route::get('entrance-tickets-variations/export/csv', [EntranceTicketVariationExportImportController::class, 'export']);
    Route::post('entrance-tickets-variations/import/csv', [EntranceTicketVariationExportImportController::class, 'import']);

    # Airport Pickup
    Route::apiResource('airport-pickups', AirportPickupController::class);

    # Inclusive
    Route::apiResource('inclusive', InclusiveController::class);
    Route::post('inclusive/{inclusive_id}/detail', [InclusiveController::class, 'storeDetail']);
    Route::delete('inclusive/{inclusive}/images/{inclusive_image}', [InclusiveController::class, 'deleteImage']);
    Route::post('inclusive/{inclusive_id}/pdf', [InclusiveController::class, 'storePdf']);
    Route::delete('inclusive/{inclusive}/pdfs/{pdf_id}', [InclusiveController::class, 'deletePdf']);

    # Destination
    Route::apiResource('destinations', DestinationController::class);
    Route::delete('destinations/{destination_id}/images/{product_image_id}', [DestinationController::class, 'deleteImage']);
    Route::post('destinations/{destination_id}/images', [DestinationController::class, 'uploadImage']);
    Route::post('destinations/import/csv', [DestinationController::class, 'import']);

    # Facility
    Route::apiResource('facilities', FacilityController::class);

    # Hotel Category
    Route::apiResource('hotel-categories', HotelCategoryController::class);

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
    Route::get('reservations/export/excel', [ReservationListExportController::class, 'export']);

    # Reservation Group By
    Route::get('reservations-hotel', [ReservationHotelController::class, 'getHotelReservations']);
    Route::get('/reservations-hotel/{id}/{product_id}', [ReservationHotelController::class, 'getHotelReservationDetail']);
    Route::get('/reservations-hotel/{id}/copy/{product_id}', [ReservationHotelController::class, 'copyBookingItemsGroup']);
    Route::get('/reservations-vantour/{id}', [ReservationHotelController::class, 'getPrivateVanTourReservationDetail']);

    # Private Vantour
    Route::apiResource('private-van-tours', PrivateVanTourController::class);
    Route::delete('private-van-tours/{id}/delete-cover-image', [PrivateVanTourController::class, 'deleteCoverImage']);

    Route::patch('private-van-tours/{id}/restore', [PrivateVanTourController::class, 'restore']);
    Route::delete('private-van-tours/{id}/force', [PrivateVanTourController::class, 'hardDelete']);

    Route::get('private-van-tours/export/csv', [PrivateVanTourExportImportController::class, 'export']);
    Route::post('private-van-tours/import/csv', [PrivateVanTourExportImportController::class, 'import']);

    # Hotel
    Route::apiResource('hotels', HotelController::class);
    Route::post('hotels/{id}/slug', [HotelController::class, 'addSlug']);

    Route::patch('hotels/{id}/restore', [HotelController::class, 'restore']);
    Route::delete('hotels/{id}/force', [HotelController::class, 'hardDelete']);

    Route::get('incomplete-hotels', [HotelController::class, 'incomplete']);
    Route::delete('hotels/{hotel}/images/{hotel_image}', [HotelController::class, 'deleteImage']);
    Route::delete('hotels/{hotel}/contracts/{hotel_contract}', [HotelController::class, 'deleteContract']);
    Route::get('hotels/export/csv', [HotelExportImportController::class, 'export']);
    Route::post('hotels/import/csv', [HotelExportImportController::class, 'import']);

    # Room
    Route::apiResource('rooms', RoomController::class);

    Route::get('rooms/{room}/price', [RoomPeriodController::class, 'index']);

    Route::patch('rooms/{id}/restore', [RoomController::class, 'restore']);
    Route::delete('rooms/{id}/force', [RoomController::class, 'hardDelete']);

    Route::get('incomplete-rooms', [RoomController::class, 'incomplete']);
    Route::delete('rooms/{room}/images/{room_image}', [RoomController::class, 'deleteImage']);
    Route::get('rooms/export/csv', [RoomExportImportController::class, 'export']);
    Route::post('rooms/import/csv', [RoomExportImportController::class, 'import']);

    Route::apiResource('rooms_items', RoitemController::class);
    Route::apiResource('rooms_groups', RofacilityController::class);
    Route::get('rooms/{room}/facilities', [RoomController::class, 'getRoomFacilities']);
    Route::post('rooms/{room}/roitems', [RoomController::class, 'addRoitems']);
    Route::delete('rooms/{room}/roitems', [RoomController::class, 'removeRoitems']);

    # Restaurant
    Route::apiResource('restaurants', RestaurantController::class);

    Route::patch('restaurants/{id}/restore', [RestaurantController::class, 'restore']);
    Route::delete('restaurants/{id}/force', [RestaurantController::class, 'hardDelete']);

    Route::delete('restaurants/{restaurant}/images/{product_image}', [RestaurantController::class, 'deleteImage']);
    Route::get('restaurants/export/csv', [RestaurantExportImportController::class, 'export']);
    Route::post('restaurants/import/csv', [RestaurantExportImportController::class, 'import']);

    # Meal
    Route::apiResource('meals', MealController::class);

    Route::patch('meals/{id}/restore', [MealController::class, 'restore']);
    Route::delete('meals/{id}/force', [MealController::class, 'hardDelete']);

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

    Route::patch('airlines/{id}/restore', [AirlineController::class, 'restore']);
    Route::delete('airlines/{id}/force', [AirlineController::class, 'hardDelete']);

    Route::get('airlines/export/csv', [AirlineExportImportController::class, 'export']);
    Route::post('airlines/import/csv', [AirlineExportImportController::class, 'import']);

    # Airline Ticket
    Route::apiResource('airline-tickets', AirlineTicketController::class);

    Route::patch('airline-tickets/{id}/restore', [AirlineTicketController::class, 'restore']);
    Route::delete('airline-tickets/{id}/force', [AirlineTicketController::class, 'hardDelete']);

    Route::get('airline-tickets/export/csv', [AirlineTicketExportImportController::class, 'export']);
    Route::post('airline-tickets/import/csv', [AirlineTicketExportImportController::class, 'import']);

    # Car Booking
    Route::get('car-bookings', [CarBookingController::class, 'index']);
    Route::get('car-bookings/{booking_item_id}/edit', [CarBookingController::class, 'edit']);
    Route::post('car-bookings/{booking_item_id}', [CarBookingController::class, 'update']);
    Route::get('car-bookings/summary', [CarBookingController::class, 'getSummary']);
    Route::get('car-bookings/complete/percentage', [CarBookingController::class, 'completePercentage']);

    Route::delete('booking-receipt/{id}', [BookingController::class, 'deleteReceipt']);
    Route::delete('reservation-receipt/{id}', [ReservationController::class, 'deleteReceipt']);
    Route::delete('confirmation-receipt/{id}', [ReservationController::class, 'deleteConfirmationReceipt']);
    Route::delete('customer-passport/{id}', [ReservationController::class, 'deleteCustomerPassport']);

    # Reservation Transaction
    Route::apiResource('reservation-transactions', ReservationTransactionController::class);
    Route::delete('reservation-transactions/{reservation_id}/{transaction_id}', [ReservationTransactionController::class, 'deleteTransaction']);

    # Product Available Schedule
    Route::apiResource('product-available-schedules', ProductAvailableScheduleController::class);

    # Attraction Activities
    Route::apiResource('attraction-activities', AttractionActivityController::class);

    # Booking Report by sale date
    Route::get('sale-report-by-date', [DashboardController::class, 'saleReportByDate']);

    # Admin Meta
    Route::get('admin-metas/sale-targets', [AdminMetaController::class, 'index']);
    Route::post('admin-metas/sale-targets', [AdminMetaController::class, 'storeSaleTarget']);

    # Place
    Route::apiResource('places', PlaceController::class);

    # Users
    Route::get('users', [UserController::class, 'index']);

    # Product Addon
    Route::apiResource('product-addons', ProductAddonController::class);

    Route::delete('booking_confirm_letter/{id}', [BookingController::class, 'deleteBookingConfirmLetter']);

    Route::get('sale-managers', [SaleManagerController::class, 'index']);
    Route::post('sale-managers/assign', [SaleManagerController::class, 'assign']);

    # File Manager
    Route::apiResource('reservations/{reservation_id}/passports', ReservationCustomerPassportController::class);
    Route::apiResource('reservations/{reservation_id}/paid-slips', ReservationPaidSlipController::class);
    Route::apiResource('reservations/{reservation_id}/booking-confirm-letters', BookingConfirmLetterController::class);
    Route::apiResource('reservations/{reservation_id}/receipt-images', ReservationExpenseReceiptController::class);
    Route::apiResource('bookings/{booking_id}/receipts', BookingReceiptController::class);

    Route::get('all/receipts', [BookingReceiptController::class, 'getall']);

    # Expense Mail & Booking request methods
    Route::apiResource('reservations/{reservation_id}/expense-mails', ReservationExpenseMailController::class);
    Route::apiResource('reservations/{reservation_id}/booking-request', ReservationBookingRequestController::class);
    Route::delete('reservations/{reservation_id}/booking-request/{id}', [ReservationBookingRequestController::class, 'destroy']);
    Route::delete('reservations/{reservation_id}/expense-mails/{id}', [ReservationExpenseMailController::class, 'destroy']);

    # Account Head & Class & Chart of Account
    Route::apiResource('account-heads', AccountHeadController::class);
    Route::apiResource('account-classes', AccountClassController::class);
    Route::apiResource('chart-of-accounts', ChartOfAccountController::class);

    # Chart of Accounts additional methods
    Route::get('/balance-due-over', [ChartOfAccountController::class, 'balanceDueOver']);

    # Account Receivable
    Route::get('/account-receivable', [BalanceDueOverController::class, 'index']);

    # Partner
    Route::apiResource('partners', PartnerController::class);

    # Assign Product to Partner
    Route::post('partners/{id}/assign-product', [PartnerController::class, 'assignProduct']);

    # Amendment
    Route::apiResource('booking-item-amendments', AmendmentController::class);
    Route::post('booking-item-amendments/{id}/reject', [AmendmentController::class, 'rejectAmendment']);

    # Case
    Route::apiResource('cases', CaseController::class);

    # Booking item verify
    Route::put('/booking/{id}/verify_status', [BookingItemVerifyController::class, 'updateVerifyStatus']);

    # Booking Item Group
    Route::post('booking-item-groups', [BookingItemGroupController::class, 'index']);
    Route::get('booking-item-groups/{booking_item_group}', [BookingItemGroupController::class, 'detail']);
    Route::put('booking-item-groups/{booking_item_group}', [BookingItemGroupController::class, 'update']);

    # Customer Document
    Route::get('booking-item-groups/{booking_item_group}/documents', [CustomerDocumentController::class, 'index']);
    Route::post('booking-item-groups/{booking_item_group}/documents', [CustomerDocumentController::class, 'store']);
    Route::put('booking-item-groups/{booking_item_group}/documents/{customer_document}', [CustomerDocumentController::class, 'update']);
    Route::delete('booking-item-groups/{booking_item_group}/documents/{customer_document}', [CustomerDocumentController::class, 'delete']);

    # order part
    Route::get('orders', [OrderAdminController::class, 'index']);
    Route::post('orders/{id}/payment', [OrderAdminController::class, 'addPayment']);
    Route::post('orders/{id}/change-to-booking', [OrderAdminController::class, 'changeOrderToBooking']);
    Route::post('orders/{id}/change-status', [OrderAdminController::class, 'changeStatus']);
    Route::delete('orders/{id}', [OrderAdminController::class, 'deleteOrder']);

    Route::get('report/orders', [OrderAdminController::class, 'reportOrderCompact']);

    # Tax Receipt
    Route::apiResource('tax-receipts', TaxReceiptController::class);
    Route::post('tax-receipts/{tax_receipt}/async-reservations', [TaxReceiptController::class, 'syncReservations']);

    # Cash book
    Route::apiResource('cash-books', CashBookController::class);

    Route::delete('cash-books/{id}/delete-image', [CashBookController::class, 'destoryCashBookImage']);

    # Cash Structure
    Route::apiResource('cash-structures', CashStructureController::class);

    # Cash Image
    Route::apiResource('cash-images', CashImageController::class);
    # Cash Image Summary Report
    Route::get('summary-report', [CashImageController::class, 'summary']);
    Route::get('/summary/export-csv', [CashImageController::class, 'exportSummaryToCsv']);
    Route::get('/parchase/export-csv', [CashImageController::class, 'exportParchaseToCsv']);

    # Summary Report Vat
    Route::get('summary-report-vat', [VatCalculationController::class, 'getMonthlySummary']);

    # Cash Image Booking
    Route::prefix('cash-image-bookings')->group(function () {
        // Standard CRUD
        Route::put('/update-and-attach/{id}', [CashImageBookingController::class, 'update']);

        // New methods for creating cash images without relatable_id
        Route::post('/create-and-attach', [CashImageBookingController::class, 'createAndAttach']);
    });

    # Storage File
    Route::prefix('admin/files')->group(function () {
        // Get overall storage stats (must be first to avoid conflicts)
        Route::get('stats', [FileController::class, 'getStorageStats']);

        // Routes for subdirectories (pdfs/batches) - these must come before single param routes
        Route::get('{type}/{subtype}/count', [FileController::class, 'countFilesWithSubdir'])
            ->where('type', '[a-zA-Z0-9_-]+')
            ->where('subtype', '[a-zA-Z0-9_-]+');

        Route::get('{type}/{subtype}/list', [FileController::class, 'listFilesWithSubdir'])
            ->where('type', '[a-zA-Z0-9_-]+')
            ->where('subtype', '[a-zA-Z0-9_-]+');

        Route::get('{type}/{subtype}/filtered', [FileController::class, 'getFilteredFilesWithSubdir'])
            ->where('type', '[a-zA-Z0-9_-]+')
            ->where('subtype', '[a-zA-Z0-9_-]+');

        Route::delete('{type}/{subtype}/{filename}', [FileController::class, 'deleteFileWithSubdir'])
            ->where('type', '[a-zA-Z0-9_-]+')
            ->where('subtype', '[a-zA-Z0-9_-]+');

        // Routes for single-level directories (pdfs, export)
        Route::get('{type}/count', [FileController::class, 'countFiles']);
        Route::get('{type}/list', [FileController::class, 'listFiles']);
        Route::get('{type}/filtered', [FileController::class, 'getFilteredFiles']);
        Route::delete('{type}/{filename}', [FileController::class, 'deleteFile']);
    });


    require __DIR__ . '/sub_routes/admin_v2.php';
});
