<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingItem;
use App\Models\ReservationAssociatedCustomer;
use App\Models\ReservationCustomerPassport;
use App\Traits\ImageManager;
use Illuminate\Http\Request;

class CustomerInformationController extends Controller
{
    use ImageManager;

    public function store(BookingItem $bookingItem, Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'passport' => 'required',
        ]);

        $associatedCustomer = ReservationAssociatedCustomer::updateOrCreate(
            ['booking_item_id' => $bookingItem->id],
            [
                'name' => $request->customer_name,
                'email' => $request->customer_email,
                'phone' => $request->customer_phone,
                'passport' => $request->customer_passport_number,
            ]
        );

        if ($request->customer_passport) {
            foreach ($request->customer_passport as $passport) {
                $fileData = $this->uploads($passport, 'passport/');
                ReservationCustomerPassport::create(['booking_item_id' => $bookingItem->id, 'file' => $fileData['fileName']]);
            }
        }

        return success($associatedCustomer);
    }
}
