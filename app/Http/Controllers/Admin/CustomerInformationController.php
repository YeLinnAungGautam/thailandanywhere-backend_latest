<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingItem;
use App\Models\ReservationAssociatedCustomer;
use App\Models\ReservationCustomerPassport;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerInformationController extends Controller
{
    use ImageManager;

    public function store(BookingItem $bookingItem, Request $request)
    {
        try {
            $request->validate([
                'name' => 'nullable',
                'email' => 'nullable|email',
                'phone' => 'nullable',
                'passport' => 'nullable',
                'customer_passport.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
            ]);

            $associatedCustomer = ReservationAssociatedCustomer::updateOrCreate(
                ['booking_item_id' => $bookingItem->id],
                [
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'passport' => $request->passport,
                ]
            );

            if ($request->customer_passport) {
                foreach ($request->customer_passport as $passport) {
                    $fileData = $this->uploads($passport, 'passport/');

                    ReservationCustomerPassport::create(['booking_item_id' => $bookingItem->id, 'file' => $fileData['fileName']]);
                }
            }

            return success($associatedCustomer);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return failedMessage($e->getMessage());
        }
    }
}
