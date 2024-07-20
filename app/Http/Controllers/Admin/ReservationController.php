<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItemDetailResource;
use App\Http\Resources\BookingItemResource;
use App\Jobs\HotelConfirmationReceiptUploadNotifierJob;
use App\Jobs\SendReservationNotifyEmailJob;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\ReservationAssociatedCustomer;
use App\Models\ReservationBookingConfirmLetter;
use App\Models\ReservationCarInfo;
use App\Models\ReservationCustomerPassport;
use App\Models\ReservationExpenseReceipt;
use App\Models\ReservationInfo;
use App\Models\ReservationPaidSlip;
use App\Models\ReservationSupplierInfo;
use App\Services\BookingItemDataService;
use App\Services\ReservationEmailNotifyService;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReservationController extends Controller
{
    use ImageManager;
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $filter = $request->query('filter');
        $serviceDate = $request->query('service_date');
        $calenderFilter = $request->query('calender_filter');
        $search = $request->input('hotel_name');
        $search_attraction = $request->input('attraction_name');

        $query = BookingItem::query()
            ->with([
                'product',
                'booking',
                'booking.customer',
                'reservationCarInfo',
                'reservationCarInfo.supplier',
                'reservationCarInfo.driver',
                'reservationCarInfo.driverInfo',
            ])
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->when($request->sale_daterange, function ($q) use ($request) {
                $dates = explode(',', $request->sale_daterange);

                $q->whereBetween('booking_items.service_date', [$dates[0], $dates[1]]);
                // $q->whereIn('booking_id', function ($q) use ($dates) {
                //     $q->select('id')
                //         ->from('bookings')
                //         ->whereBetween('booking_date', [$dates[0], $dates[1]]);
                // });
            })
            ->when($request->booking_date, function ($q) use ($request) {
                $q->whereDate('booking_items.created_at', $request->booking_date);
            })
            ->when($request->booking_daterange, function ($query) use ($request) {
                $query->whereHas('booking', function ($q) use ($request) {
                    $dates = explode(',', $request->booking_daterange);

                    $q->whereBetween('bookings.booking_date', $dates);
                });
            });

        if ($serviceDate) {
            $query->whereDate('booking_items.service_date', $serviceDate);
        };

        $productType = $request->query('product_type');
        $crmId = $request->query('crm_id');
        $oldCrmId = $request->query('old_crm_id');

        if ($crmId) {
            // $query->whereHas('booking', function ($q) use ($crmId) {
            //     $q->where('crm_id', 'LIKE', "%{$crmId}%");
            // });
            $query->where('booking_items.crm_id', 'LIKE', "%{$crmId}%");
        }

        if ($oldCrmId) {
            $query->whereHas('booking', function ($q) use ($oldCrmId) {
                $q->where('bookings.past_crm_id', 'LIKE', "%{$oldCrmId}%");
            });
        }

        if ($request->user_id) {
            $userId = $request->user_id;
            $query->whereHas('booking', function ($q) use ($userId) {
                $q->where('bookings.created_by', $userId)->orWhere('bookings.past_user_id', $userId);
            });
        }

        if ($productType) {
            $query->where('booking_items.product_type', $productType);
        }

        if ($request->reservation_status) {
            $query->where('booking_items.reservation_status', $request->reservation_status);
        }

        if ($request->booking_status) {
            $query->where('booking_items.reservation_status', $request->booking_status);
        }

        if ($request->customer_payment_status) {
            $query->whereIn('booking_items.booking_id', function ($q) use ($request) {
                $q->select('id')
                    ->from('bookings')
                    ->where('payment_status', $request->customer_payment_status);
            });
        }

        if ($request->expense_status) {
            $query->where('booking_items.payment_status', $request->expense_status);
        }

        if ($calenderFilter == true) {
            $query->where('booking_items.product_type', 'App\Models\PrivateVanTour')->orWhere('booking_items.product_type', 'App\Models\GroupTour');
        }

        if($search) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            });
        }

        if($search_attraction) {
            $query->whereHas('variation', function ($q) use ($search_attraction) {
                $q->where('name', 'LIKE', "%{$search_attraction}%");
            });
        }

        if (Auth::user()->role === 'super_admin' || Auth::user()->role === 'reservation') {
            if ($filter) {
                if ($filter === 'past') {
                    $query->whereHas('booking', function ($q) {
                        $q->where('bookings.is_past_info', true)->whereNotNull('past_user_id');
                    });
                } elseif ($filter === 'current') {
                    $query->whereHas('booking', function ($q) {
                        $q->where('bookings.is_past_info', false)->whereNull('past_user_id');
                    });
                }
            }
        } else {
            $query->whereHas('booking', function ($q) {
                $q->where('bookings.created_by', Auth::id())->orWhere('past_user_id', Auth::id());
            });

            if ($filter) {
                if ($filter === 'past') {
                    $query->whereHas('booking', function ($q) {
                        $q->where('bookings.is_past_info', true)->where('past_user_id', Auth::id())->whereNotNull('past_user_id');
                    });
                } elseif ($filter === 'current') {
                    $query->whereHas('booking', function ($q) {
                        $q->where('bookings.created_by', Auth::id())->whereNull('past_user_id');
                    });
                }
            }
        }

        $this->orderByKey($query, $request);

        $query->select('booking_items.*');

        $data = $query->paginate($limit);

        return $this->success(BookingItemResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Reservation List');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = BookingItem::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new BookingItemResource($find), 'Booking Item Detail');
    }

    public function copyDetail(string $id)
    {
        $booking_item = BookingItem::find($id);

        if (!$booking_item) {
            return $this->error(null, 'Data not found', 404);
        }

        $booking_item->load(
            'booking:id,crm_id,grand_total,booking_date,payment_status,payment_method',
            'product'
        );

        return $this->success(new BookingItemDetailResource($booking_item), 'Booking Item Detail Copy');
    }

    /**
    * Print Invoice for the reservation entrance category.
    */
    public function printReservation(Request $request, string $id)
    {

        $booking = BookingItem::find($id);

        if($booking == '') {
            abort(404);
        }

        $data = new BookingItemResource($booking);

        $customers[] = $booking->booking->customer;

        $pdf = Pdf::setOption([
            'fontDir' => public_path('/fonts')
        ])->loadView('pdf.reservation_receipt', compact('data', 'customers'));

        return $pdf->stream();

    }

    public function printReservationHotel(Request $request, string $id)
    {

        $booking = BookingItem::find($id);

        if($booking == '') {
            abort(404);
        }
        $data = new BookingItemResource($booking);

        $hotel[] = $booking->booking->hotel;

        $pdf = Pdf::setOption([
            'fontDir' => public_path('/fonts')
        ])->loadView('pdf.reservation_hotel_receipt', compact('data', 'hotel'));

        return $pdf->stream();

    }

    public function printReservationVantour(Request $request, string $id)
    {
        $booking = BookingItem::find($id);

        if($booking == '') {
            abort(404);
        }

        $data = new BookingItemResource($booking);

        $hotels[] = $booking->booking->vantour;
        $total_cost = (new BookingItemDataService($booking))->getTotalCost();
        $sale_price = (new BookingItemDataService($booking))->getSalePrice();

        $pdf = Pdf::setOption([
            'fontDir' => public_path('/fonts')
        ])->loadView('pdf.reservation_vantour', compact('data', 'hotels', 'total_cost', 'sale_price'));

        return $pdf->stream();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $find = BookingItem::find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $data = [
            'service_date' => $request->service_date ?? $find->service_date,
            'quantity' => $request->quantity ?? $find->quantity,
            'total_guest' => $request->total_guest ?? $find->total_guest,
            'selling_price' => $request->selling_price ?? $find->selling_price,
            'duration' => $request->duration ?? $find->duration,
            'cost_price' => $request->cost_price ?? $find->cost_price,
            'total_cost_price' => $request->total_cost_price ?? $find->total_cost_price,
            'payment_method' => $request->payment_method ?? $find->payment_method,
            'payment_status' => $request->payment_status ?? $find->payment_status,
            'exchange_rate' => $request->exchange_rate ?? $find->exchange_rate,
            'reservation_status' => $request->reservation_status ?? $find->reservation_status,
            'slip_code' => $request->slip_code ?? $find->slip_code,
            'expense_amount' => $request->expense_amount ?? $find->expense_amount,
            'comment' => $request->comment ?? $find->comment,
            'route_plan' => $request->route_plan ?? $find->route_plan,
            'special_request' => $request->special_request ?? $find->special_request,
            'dropoff_location' => $request->dropoff_location ?? $find->dropoff_location,
            'pickup_location' => $request->pickup_location ?? $find->pickup_location,
            'pickup_time' => $request->pickup_time ?? $find->pickup_time,
        ];


        if ($file = $request->file('confirmation_letter')) {
            if ($find->confirmation_letter) {
                Storage::delete('public/images/' . $find->confirmation_letter);
            }

            $fileData = $this->uploads($file, 'files/');
            $data['confirmation_letter'] = $fileData['fileName'];
        }

        if ($request->customer_passport) {
            foreach ($request->customer_passport as $passport) {
                $fileData = $this->uploads($passport, 'passport/');
                ReservationCustomerPassport::create(['booking_item_id' => $find->id, 'file' => $fileData['fileName']]);
            }
        }

        $find->update($data);


        // check all reservation status and update booking reservation status

        $booking = Booking::find($find->booking_id);

        // Check if all item's status is 'confirm'
        $allConfirmed = $booking->items->every(function ($item) {
            return $item->reservation_status === 'reserved';
        });

        if ($allConfirmed) {
            $booking->update(['reservation_status' => 'confirmed']);
        }

        return $this->success(new BookingItemResource($find), 'Successfully updated');
    }

    public function updateInfo(Request $request, $id)
    {
        $bookingItem = BookingItem::find($id);

        if (!$bookingItem) {
            return $this->error(null, 'Data not found', 404);
        }

        $findInfo = ReservationInfo::where('booking_item_id', $bookingItem->id)->first();
        if (!$findInfo) {
            $saveData = [
                'booking_item_id' => $bookingItem->id,
                'customer_feedback' => $request->customer_feedback,
                'customer_score' => $request->customer_score,
                'driver_score' => $request->driver_score,
                'product_score' => $request->product_score,
                'other_info' => $request->other_info,
                'payment_method' => $request->payment_method,
                'bank_name' => $request->bank_name,
                'bank_account_number' => $request->bank_account_number,
                'expense_amount' => $request->expense_amount,
                'cost' => $request->cost,
                'payment_status' => $request->payment_status,
                'payment_due' => $request->payment_due,
            ];

            $save = ReservationInfo::create($saveData);

            if ($request->paid_slip) {
                $paid_slip_names = [];
                foreach ($request->paid_slip as $image) {
                    $fileData = $this->uploads($image, 'images/');
                    ReservationPaidSlip::create(['booking_item_id' => $save->booking_item_id, 'file' => $fileData['fileName']]);

                    array_push($paid_slip_names, $fileData['fileName']);
                }

                HotelConfirmationReceiptUploadNotifierJob::dispatch($paid_slip_names, $bookingItem);
            }

            if ($request->customer_passport) {
                foreach ($request->customer_passport as $passport) {
                    $fileData = $this->uploads($passport, 'files/');
                    ReservationCustomerPassport::create(['booking_item_id' => $save->booking_item_id, 'file' => $fileData['fileName']]);
                }
            }

        } else {
            $findInfo->customer_feedback = $request->customer_feedback ?? $findInfo->customer_feedback;
            $findInfo->customer_score = $request->customer_score ?? $findInfo->customer_score;
            $findInfo->driver_score = $request->driver_score ?? $findInfo->driver_score;
            $findInfo->product_score = $request->product_score ?? $findInfo->product_score;
            $findInfo->other_info = $request->other_info ?? $findInfo->other_info;
            $findInfo->payment_method = $request->payment_method ?? $findInfo->payment_method;
            $findInfo->payment_status = $request->payment_status ?? $findInfo->payment_status;
            $findInfo->payment_due = $request->payment_due ?? $findInfo->payment_due;
            $findInfo->payment_receipt = $request->payment_receipt ?? $findInfo->payment_receipt;
            $findInfo->expense_amount = $request->expense_amount ?? $findInfo->expense_amount;
            $findInfo->bank_name = $request->bank_name ?? $findInfo->bank_name;
            $findInfo->cost = $request->cost ?? $findInfo->cost;
            $findInfo->bank_account_number = $request->bank_account_number ?? $findInfo->bank_account_number;

            $findInfo->update();

            if ($request->paid_slip) {
                $paid_slip_names = [];
                foreach ($request->paid_slip as $image) {
                    $fileData = $this->uploads($image, 'images/');
                    ReservationPaidSlip::create(['booking_item_id' => $findInfo->booking_item_id, 'file' => $fileData['fileName']]);

                    array_push($paid_slip_names, $fileData['fileName']);
                }

                HotelConfirmationReceiptUploadNotifierJob::dispatch($paid_slip_names, $bookingItem);
            }
        }

        $isEntranceTicketType = $bookingItem->product_type === 'App\Models\EntranceTicket';
        $isHotelType = $bookingItem->product_type === 'App\Models\Hotel';

        if (!$isEntranceTicketType && !$isHotelType) {
            $findCarInfo = ReservationCarInfo::where('booking_item_id', $bookingItem->id)->first();
            if (!$findCarInfo) {
                $data = [
                    // 'driver_name' => $request->driver_name,
                    // 'supplier_name' => $request->supplier_name,
                    // 'car_number' => $request->car_number,
                    'booking_item_id' => $bookingItem->id,
                    'driver_contact' => $request->driver_contact,
                    'account_holder_name' => $request->account_holder_name,
                    'supplier_id' => $request->supplier_id,
                    'driver_id' => $request->driver_id,
                    'driver_info_id' => $request->driver_info_id,
                ];

                if ($file = $request->file('car_photo')) {
                    $fileData = $this->uploads($file, 'images/');
                    $data['car_photo'] = $fileData['fileName'];
                }
                ReservationCarInfo::create($data);
            } else {
                // $findCarInfo->driver_name = $request->driver_name ?? $findCarInfo->driver_name;
                // $findCarInfo->supplier_name = $request->supplier_name ?? $findCarInfo->supplier_name;
                // $findCarInfo->car_number = $request->car_number ?? $findCarInfo->car_number;
                $findCarInfo->driver_contact = $request->driver_contact ?? $findCarInfo->driver_contact;
                $findCarInfo->supplier_id = $request->supplier_id ?? $findCarInfo->supplier_id;
                $findCarInfo->driver_id = $request->driver_id ?? $findCarInfo->driver_id;
                $findCarInfo->driver_info_id = $request->driver_info_id ?? $findCarInfo->driver_info_id;
                $findCarInfo->account_holder_name = $request->account_holder_name ?? $findCarInfo->account_holder_name;

                if ($file = $request->file('car_photo')) {
                    if ($findCarInfo->car_photo) {
                        Storage::delete('public/images/' . $findCarInfo->car_photo);
                    }
                    $fileData = $this->uploads($file, 'images/');
                    $findCarInfo->car_photo = $fileData['fileName'];
                }

                $findCarInfo->update();
            }

            if ($request->receipt_image) {
                foreach ($request->receipt_image as $image) {
                    if(isset($findInfo->booking_item_id)) {
                        $fileData = $this->uploads($image, 'images/');

                        ReservationExpenseReceipt::create([
                            'booking_item_id' => $findInfo->booking_item_id,
                            'file' => $fileData['fileName']
                        ]);
                    }
                }
            }

        } else {
            $findInfo = ReservationSupplierInfo::where('booking_item_id', $bookingItem->id)->first();
            if (!$findInfo) {
                $data = [
                    'booking_item_id' => $bookingItem->id,
                    'ref_number' => $request->ref_number,
                    'supplier_name' => $request->supplier_name,
                ];

                //                if ($file = $request->file('receipt_image')) {
                //                    $fileData = $this->uploads($file, 'images/');
                //                    ReservationExpenseReceipt::create(['booking_item_id' => $bookingItem->id, 'file' => $fileData['fileName']]);
                //                }

                if ($request->receipt_image) {
                    foreach ($request->receipt_image as $image) {
                        $fileData = $this->uploads($image, 'images/');
                        ReservationExpenseReceipt::create(['booking_item_id' => $bookingItem->id, 'file' => $fileData['fileName']]);
                    }
                }

                if ($file = $request->file('booking_confirm_letter')) {
                    $fileData = $this->uploads($file, 'images/');
                    ReservationBookingConfirmLetter::create(['booking_item_id' => $bookingItem->id, 'file' => $fileData['fileName']]);
                }


                ReservationSupplierInfo::create($data);
            } else {

                $findInfo->ref_number = $request->ref_number ?? $findInfo->ref_number;
                $findInfo->supplier_name = $request->supplier_name ?? $findInfo->supplier_name;

                //                if ($file = $request->file('receipt_image')) {
                //                    $fileData = $this->uploads($file, 'images/');
                //                    ReservationExpenseReceipt::create(['booking_item_id' => $findInfo->booking_item_id, 'file' => $fileData['fileName']]);
                //                }

                if ($request->receipt_image) {
                    foreach ($request->receipt_image as $image) {
                        $fileData = $this->uploads($image, 'images/');
                        ReservationExpenseReceipt::create(['booking_item_id' => $findInfo->booking_item_id, 'file' => $fileData['fileName']]);
                    }
                }

                if ($file = $request->file('booking_confirm_letter')) {
                    $fileData = $this->uploads($file, 'images/');
                    ReservationBookingConfirmLetter::create(['booking_item_id' => $findInfo->booking_item_id, 'file' => $fileData['fileName']]);
                }


                $findInfo->update();
            }
        }

        if ($request->customer_passport) {
            foreach ($request->customer_passport as $passport) {
                $fileData = $this->uploads($passport, 'files/');
                ReservationCustomerPassport::create(['booking_item_id' => $findInfo->booking_item_id, 'file' => $fileData['fileName']]);
            }
        }

        if($request->is_associated == 1) {
            if(ReservationAssociatedCustomer::where('booking_item_id', '=', $findInfo->booking_item_id)->count() > 0) {
                ReservationAssociatedCustomer::where('booking_item_id', '=', $findInfo->booking_item_id)->update([
                    'name' => $request->customer_name,
                    'phone' => $request->customer_phone,
                    'passport' => $request->customer_passport_number,
                ]);

            } else {

                ReservationAssociatedCustomer::create([
                    'booking_item_id' => $findInfo->booking_item_id,
                    'name' => $request->customer_name,
                    'phone' => $request->customer_phone,
                    'passport' => $request->customer_passport_number,
                ]);

                BookingItem::where('id', $findInfo->booking_item_id)->update(['is_associated' => '1']);
            }
        } elseif($request->is_associated == 0) {

            if(isset($findInfo->booking_item_id)) {
                ReservationAssociatedCustomer::where('booking_item_id', $findInfo->booking_item_id)->delete();

                BookingItem::where('id', $findInfo->booking_item_id)->update(['is_associated' => '0']);
            }
        }

        return $this->success(new BookingItemResource($bookingItem), 'Successfully updated');
    }

    public function deleteReceipt($id)
    {
        $find = ReservationExpenseReceipt::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        Storage::delete('public/images/' . $find->file);
        $find->delete();

        return $this->success(null, 'Successfully deleted');

    }

    public function deleteConfirmationReceipt($id)
    {
        $find = ReservationPaidSlip::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        Storage::delete('public/images/' . $find->file);
        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function deleteCustomerPassport($id)
    {
        $find = ReservationCustomerPassport::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        Storage::delete('public/files/' . $find->file);
        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function sendNotifyEmail(BookingItem $booking_item, Request $request)
    {
        $request->validate([
            'mail_subject' => 'required',
            'mail_body' => 'required'
        ]);

        try {
            $attachments = ReservationEmailNotifyService::saveAttachToTemp($request->attachments);

            dispatch(new SendReservationNotifyEmailJob(
                $request->mail_to,
                $request->mail_subject,
                $request->sent_to_default,
                $request->mail_body,
                $booking_item,
                $attachments
            ));

            return $this->success(null, 'Reservation notify email is successfully sent.', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage(), 500);
        }
    }

    private function orderByKey($query, $request)
    {
        switch ($request->order_by) {
            case 'crm_id':
                $query->orderBy('crm_id', $request->order_direction ?? 'asc');

                break;

            case 'customer_name':
                $query->orderBy('customers.name', $request->order_direction ?? 'asc');

                break;

            case 'product_type':
                $query->orderBy('product_type', $request->order_direction ?? 'asc');

                break;

                // case 'product_name':
                //     $query->orderBy('product.name', $request->order_direction ?? 'asc');

                //     break;

                // case 'variation_name':
                //     $query->orderBy('specificVariation.name', $request->order_direction ?? 'asc');

                //     break;

            case 'payment_status':
                $query->orderBy('bookings.payment_status', $request->order_direction ?? 'asc');

                break;

            case 'reservation_status':
                $query->orderBy('reservation_status', $request->order_direction ?? 'asc');

                break;

            case 'expense_status':
                $query->orderBy('payment_status', $request->order_direction ?? 'asc');

                break;

            case 'service_date':
                $query->orderBy('service_date', $request->order_direction ?? 'asc');

                break;

            default:
                $query->orderBy('booking_items.created_at', $request->order_direction ?? 'desc');

                break;
        }
    }
}
