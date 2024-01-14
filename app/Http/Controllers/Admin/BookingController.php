<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingReceipt;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BookingController extends Controller
{
    use ImageManager;
    use HttpResponses;


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $crmId = $request->query('crm_id');

        $filter = $request->query('filter');
        $paymentStatus = $request->query('status');


        $query = Booking::query()
            ->when($request->sale_date_order_by, function ($q) use ($request) {
                $order_by = $request->sale_date_order_by == 'desc' ? 'desc' : 'asc';
                $q->orderBy('booking_date', $order_by);
            });

        //        if (!Auth::user()->is_super) {
        //            $query->where('created_by', Auth::id())->orWhere('past_user_id', Auth::id());
        //        }

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($crmId) {
            $query->where('crm_id', 'LIKE', "%{$crmId}%");
        }

        if (Auth::user()->role === 'super_admin') {
            if ($filter && $filter !== "") {
                if ($filter === 'all') {
                } elseif ($filter === 'past') {
                    $query->where('is_past_info', true)->whereNotNull('past_user_id');
                } elseif ($filter === 'current') {
                    $query->whereNull('past_user_id');
                }
            }

        } else {

            $query->where('created_by', Auth::id())->orWhere('past_user_id', Auth::id());

            if ($filter && $filter !== "") {
                if ($filter === 'all') {
                    $query->where('created_by', Auth::id())->orWhere('past_user_id', Auth::id());
                } elseif ($filter === 'past') {
                    $query->where('is_past_info', true)->where('past_user_id', Auth::id())->whereNotNull('past_user_id');
                } elseif ($filter === 'current') {
                    $query->where('created_by', Auth::id())->whereNull('past_user_id');
                }
            }
        }

        if ($request->input('customer_name')) {
            $customerName = $request->input('customer_name');
            $query->whereHas('customer', function ($q) use ($customerName) {
                return $q->where('name', 'LIKE', "%{$customerName}%");
            });
        }

        if ($request->input('balance_due_date')) {
            $query->whereDate('balance_due_date', $request->input('balance_due_date'));
        }

        if ($request->input('booking_status')) {
            $query->where('reservation_status', $request->input('booking_status'));
        }

        if ($request->input('sale_date')) {
            $query->whereDate('booking_date', $request->input('sale_date'));
        }


        $data = $query->paginate($limit);

        if ($paymentStatus && $paymentStatus !== "all") {
            $query = $query->where('payment_status', $paymentStatus);
            $data = $query->paginate($limit);
        }


        $query->orderBy('created_at', 'desc');

        return $this->success(BookingResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Booking List');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BookingRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = [
                'customer_id' => $request->customer_id,
                'sold_from' => $request->sold_from,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_status,
                'payment_currency' => $request->payment_currency,
                'booking_date' => $request->booking_date,
                'bank_name' => $request->bank_name,
                'money_exchange_rate' => $request->money_exchange_rate,
                'sub_total' => $request->sub_total,
                'grand_total' => $request->grand_total,
                'deposit' => $request->deposit,
                'balance_due' => $request->balance_due,
                'balance_due_date' => $request->balance_due_date,
                'discount' => $request->discount,
                'comment' => $request->comment,
                'is_past_info' => $request->is_past_info ?? false,
                'past_user_id' => $request->past_user_id,
                'past_crm_id' => $request->past_crm_id,
                'created_by' => Auth::id(),
                'reservation_status' => "awaiting",
                'payment_notes' => $request->payment_notes,
                'is_inclusive' => $request->is_inclusive ? $request->is_inclusive : 0,
                'inclusive_name' => $request->inclusive_name ?? null,
                'inclusive_quantity' => $request->inclusive_quantity ?? null,
                'inclusive_rate' => $request->inclusive_rate ?? null,
                'inclusive_start_date' => $request->inclusive_start_date ?? null,
                'inclusive_end_date' => $request->inclusive_end_date ?? null,
            ];

            $save = Booking::create($data);

            if ($request->receipt_image) {
                foreach ($request->receipt_image as $image) {
                    $fileData = $this->uploads($image, 'images/');
                    BookingReceipt::create(['booking_id' => $save->id, 'image' => $fileData['fileName']]);
                }
            }

            foreach ($request->items as $key => $item) {
                $data = [
                    'booking_id' => $save->id,
                    'crm_id' => $save->crm_id . '_' . str_pad($key + 1, 3, '0', STR_PAD_LEFT),
                    'product_type' => $item['product_type'],
                    'room_number' => $item['room_number'] ?? null,
                    'product_id' => $item['product_id'],
                    'car_id' => isset($item['car_id']) ? $item['car_id'] : null,
                    'room_id' => isset($item['room_id']) ? $item['room_id'] : null,
                    'variation_id' => isset($item['variation_id']) ? $item['variation_id'] : null,
                    'service_date' => $item['service_date'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                    'total_guest' => $item['total_guest'] ?? null,
                    'duration' => $item['duration'] ?? null,
                    'selling_price' => $item['selling_price'] ?? null,
                    'cost_price' => $item['cost_price'] ?? null,
                    'payment_method' => $item['payment_method'] ?? null,
                    'payment_status' => $item['payment_status'] ?? 'not_paid',
                    'exchange_rate' => $item['exchange_rate'] ?? null,
                    'comment' => $item['comment'] ?? null,
                    'amount' => $item['amount'] ?? null,
                    'days' => $item['days'] ?? null,
                    'special_request' => isset($item['special_request']) ? $item['special_request'] : null,
                    'route_plan' => isset($item['route_plan']) ? $item['route_plan'] : null,
                    'pickup_location' => isset($item['pickup_location']) ? $item['pickup_location'] : null,
                    'pickup_time' => isset($item['pickup_time']) ? $item['pickup_time'] : null,
                    'dropoff_location' => isset($item['dropoff_location']) ? $item['dropoff_location'] : null,
                    'checkin_date' => isset($item['checkin_date']) ? $item['checkin_date'] : null,
                    'checkout_date' => isset($item['checkout_date']) ? $item['checkout_date'] : null,
                    'reservation_status' => $item['reservation_status'] ?? "awaiting",
                    'slip_code' => $request->slip_code,
                    'is_inclusive' => $item['reservation_status'] == 'undefined' ? "1" : "0" ,
                ];

                if (isset($request->items[$key]['customer_attachment'])) {
                    $attachment = $request->items[$key]['customer_attachment'];
                    $fileData = $this->uploads($attachment, 'attachments/');
                    $data['customer_attachment'] = $fileData['fileName'];
                }

                if (isset($request->items[$key]['receipt_image'])) {
                    $receiptImage = $request->items[$key]['receipt_image'];
                    if ($receiptImage) {
                        $fileData = $this->uploads($receiptImage, 'images/');
                        $data['receipt_image'] = $fileData['fileName'];
                    }
                }

                if (isset($request->items[$key]['confirmation_letter'])) {
                    $file = $request->items[$key]['confirmation_letter'];
                    if ($file) {
                        $fileData = $this->uploads($file, 'files/');
                        $data['confirmation_letter'] = $fileData['fileName'];
                    }
                }

                BookingItem::create($data);
            }

            DB::commit();

            return $this->success(new BookingResource($save), 'Successfully created');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = Booking::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new BookingResource($find), 'Booking Detail');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $find = Booking::find($id);

            if (!$find) {
                return $this->error(null, 'Data not found', 404);
            }

            $data = [
                'customer_id' => $request->customer_id ?? $find->customer_id,
                'is_past_info' => $request->is_past_info ?? $find->is_past_info,
                'past_user_id' => $request->past_user_id ?? $find->past_user_id,
                'past_crm_id' => $request->past_crm_id ?? $find->past_crm_id,
                'sold_from' => $request->sold_from ?? $find->sold_from,
                'payment_method' => $request->payment_method ?? $find->payment_method,
                'payment_status' => $request->payment_status ?? $find->payment_status,
                'payment_currency' => $request->payment_currency ?? $find->payment_currency,
                'booking_date' => $request->booking_date ?? $find->booking_date,
                'bank_name' => $request->bank_name ?? $find->bank_name,
                'money_exchange_rate' => $request->money_exchange_rate ?? $find->money_exchange_rate,
                'comment' => $request->comment ?? $find->comment,
                'sub_total' => $request->sub_total ?? $find->sub_total,
                'grand_total' => $request->grand_total ?? $find->grand_total,
                'deposit' => $request->deposit ?? $find->deposit,
                'balance_due' => $request->balance_due ?? $find->balance_due,
                'balance_due_date' => $request->balance_due_date ?? $find->balance_due_date,
                'discount' => $request->discount ?? $find->discount,
                "reservation_status" => 'awaiting',
                'payment_notes' => $request->payment_notes,

                'is_inclusive' => $request->is_inclusive ? $request->is_inclusive : $find->is_inclusive,
                'inclusive_name' => $request->inclusive_name ?? $find->inclusive_name,
                'inclusive_quantity' => $request->inclusive_quantity ?? $find->inclusive_quantity,
                'inclusive_rate' => $request->inclusive_rate ?? $find->inclusive_rate,
                'inclusive_start_date' => $request->inclusive_start_date ?? $find->inclusive_start_date,
                'inclusive_end_date' => $request->inclusive_end_date ?? $find->inclusive_end_date,
            ];

            $find->update($data);

            if ($request->receipt_image) {
                foreach ($request->receipt_image as $image) {
                    $fileData = $this->uploads($image, 'images/');
                    BookingReceipt::create(['booking_id' => $find->id, 'image' => $fileData['fileName']]);
                }
            }

            if ($request->items) {
                // foreach ($find->items as $key => $item) {
                // if ($item->receipt_image) {
                //     Storage::delete('public/images/' . $item->receipt_image);
                // }

                // if ($item->confirmation_letter) {
                //     Storage::delete('public/files/' . $item->confirmation_letter);
                // }

                // $deleted_reservation_ids[] = $item->id;

                // BookingItem::where('id', $item->id)->delete();
                // }

                $booking_item_ids = $find->items()->pluck('id')->toArray();
                $request_item_ids = collect($request->items)->pluck('reservation_id')->toArray();
                $delete_item_ids = [];

                foreach($booking_item_ids as $booking_item_id) {
                    if(!in_array($booking_item_id, $request_item_ids)) {
                        $delete_item_ids[] = $booking_item_id;
                    }
                }

                foreach($delete_item_ids as $delete_item) {
                    $d_item = BookingItem::find($delete_item);

                    if ($d_item->receipt_image) {
                        Storage::delete('public/images/' . $d_item->receipt_image);
                    }

                    if ($d_item->confirmation_letter) {
                        Storage::delete('public/files/' . $d_item->confirmation_letter);
                    }

                    $d_item->delete();
                }

                foreach ($request->items as $key => $item) {
                    $data = [
                        'booking_id' => $find->id,
                        'crm_id' => $find->crm_id . '_' . str_pad($key + 1, 3, '0', STR_PAD_LEFT),
                        'product_type' => $item['product_type'],
                        'room_number' => $item['room_number'] ?? null,
                        'product_id' => $item['product_id'],
                        'car_id' => isset($item['car_id']) ? $item['car_id'] : null,
                        'room_id' => isset($item['room_id']) ? $item['room_id'] : null,
                        'variation_id' => isset($item['variation_id']) ? $item['variation_id'] : null,
                        'service_date' => $item['service_date'],
                        'quantity' => $item['quantity'],
                        'total_guest' => $item['total_guest'] ?? null,
                        'days' => isset($item['days']) ? $item['days'] : null,
                        'special_request' => isset($item['special_request']) ? $item['special_request'] : null,
                        'route_plan' => isset($item['route_plan']) ? $item['route_plan'] : null,
                        'pickup_location' => isset($item['pickup_location']) ? $item['pickup_location'] : null,
                        'pickup_time' => isset($item['pickup_time']) ? $item['pickup_time'] : null,
                        'dropoff_location' => isset($item['dropoff_location']) ? $item['dropoff_location'] : null,
                        'duration' => $item['duration'] ?? null,
                        'selling_price' => $item['selling_price'] ?? null,
                        'amount' => $item['amount'] ?? null,
                        'cost_price' => $item['cost_price'] ?? null,
                        'payment_method' => $item['payment_method'] ?? null,
                        'payment_status' => $item['payment_status'] ?? 'not_paid',
                        'exchange_rate' => $item['exchange_rate'] ?? null,
                        'comment' => $item['comment'] ?? null,
                        'checkin_date' => isset($item['checkin_date']) ? $item['checkin_date'] : null,
                        'checkout_date' => isset($item['checkout_date']) ? $item['checkout_date'] : null,
                        'reservation_status' => $item['reservation_status'] ?? "awaiting",
                        'is_inclusive' => $item['reservation_status'] == 'undefined' ? "1" : "0" ,
                    ];

                    if (isset($request->items[$key]['receipt_image'])) {
                        $receiptImage = $request->items[$key]['receipt_image'];
                        if ($receiptImage) {
                            $fileData = $this->uploads($receiptImage, 'images/');
                            $data['receipt_image'] = $fileData['fileName'];
                        }
                    }

                    if (isset($request->items[$key]['customer_attachment'])) {
                        $attachment = $request->items[$key]['customer_attachment'];
                        $fileData = $this->uploads($attachment, 'attachments/');
                        $data['customer_attachment'] = $fileData['fileName'];
                    }

                    if (isset($request->items[$key]['confirmation_letter'])) {
                        $file = $request->items[$key]['confirmation_letter'];
                        if ($file) {
                            $fileData = $this->uploads($file, 'files/');
                            $data['confirmation_letter'] = $fileData['fileName'];
                        }
                    }

                    if(
                        !isset($item['reservation_id']) ||
                        $item['reservation_id'] === 'undefined' ||
                        $item['reservation_id'] == 'null'
                    ) {
                        BookingItem::create($data);
                    } else {
                        $booking_item = BookingItem::find($item['reservation_id']);

                        $booking_item->update($data);
                    }
                }
            }

            DB::commit();

            return $this->success(new BookingResource($find), 'Successfully updated');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return $this->error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $find = Booking::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        foreach ($find->items as $item) {
            if ($item->receipt_image) {
                Storage::delete('public/images/' . $item->receipt_image);
            }
        }

        $find->items()->delete();

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function printReceipt(Request $request, string $id)
    {
        if ($request->query('paid') && $request->query('paid') === 1) {
            $booking = Booking::where('id', $id)->with(['customer', 'items' => function ($q) {
                $q->where('payment_status', 'fully_paid')->where('is_inclusive', '0');
            }, 'createdBy'])->first();
        } else {
            $booking = Booking::where('id', $id)->with(['customer', 'items' => function ($q) {
                $q->where('is_inclusive', '0');
            }, 'createdBy'])->first();
        }

        $data = new BookingResource($booking);
        $pdf_view = 'pdf.booking_receipt';

        if($booking->is_inclusive) {
            $pdf_view = 'pdf.inclusive_booking_receipt';
        }

        $pdf = Pdf::setOption([
            'fontDir' => public_path('/fonts')
        ])->loadView($pdf_view, compact('data'));

        return $pdf->stream();
    }

    public function deleteReceipt($id)
    {
        $find = BookingReceipt::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        Storage::delete('public/images/' . $find->image);
        $find->delete();

        return $this->success(null, 'Successfully deleted');

    }
}
