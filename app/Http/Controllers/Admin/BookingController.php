<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Jobs\ArchiveSaleJob;
use App\Jobs\PersistBookingItemGroupJob;
use App\Jobs\SendSaleDepositUpdateEmailJob;
use App\Jobs\UpdateBookingDatesJob;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingReceipt;
use App\Models\InclusiveProduct;
use App\Models\Order;
use App\Models\ReservationBookingConfirmLetter;
use App\Services\Manager\BookingManager;
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

    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $crmId = $request->query('crm_id');
        $filter = $request->query('filter');
        $paymentStatus = $request->query('status');
        $connectionStatus = $request->query('connection_status'); // New filter for connected/not_connected
        $sortBy = $request->query('sort_by', 'created_at'); // New sorting parameter
        $sortDirection = $request->query('sort_direction', 'desc'); // Direction of sorting

        $query = Booking::query()
            // ->when($request->sale_date_order_by, function ($q) use ($request) {
            //     $order_by = $request->sale_date_order_by == 'desc' ? 'desc' : 'asc';
            //     $q->orderBy('booking_date', $order_by);
            // })
            ->when($request->inclusive_only, function ($q) use ($request) {
                $is_inclusive = $request->inclusive_only ? 1 : 0;
                $q->where('is_inclusive', $is_inclusive);
            })
            ->when($request->created_by, fn ($query) => $query->where('created_by', $request->created_by));

        // Connection status filter (connected/not_connected)
        if ($connectionStatus) {
            if ($connectionStatus === 'connected') {
                $query->has('user');  // Check if the relationship exists
            } elseif ($connectionStatus === 'not_connected') {
                $query->doesntHave('user');  // Check if the relationship doesn't exist
            }
        }

        // Search filter
        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($crmId) {
            $query->where('crm_id', 'LIKE', "%{$crmId}%");
        }

        // Booking date range filter
        if ($request->has('booking_date_from') && $request->has('booking_date_to')) {
            $query->whereBetween('booking_date', [
                $request->input('booking_date_from'),
                $request->input('booking_date_to')
            ]);
        } elseif ($request->has('booking_date_from')) {
            $query->whereDate('booking_date', '>=', $request->input('booking_date_from'));
        } elseif ($request->has('booking_date_to')) {
            $query->whereDate('booking_date', '<=', $request->input('booking_date_to'));
        }

        if (Auth::user()->role === 'super_admin' || Auth::user()->role === 'reservation' || Auth::user()->role === 'auditor') {
            if ($filter && $filter !== "") {
                if ($filter === 'all') {
                    // No filter needed
                } elseif ($filter === 'past') {
                    $query->where('is_past_info', true)->whereNotNull('past_user_id');
                } elseif ($filter === 'current') {
                    $query->whereNull('past_user_id');
                }
            }
        } else {
            $query->where(function ($q) {
                $q->where('created_by', Auth::id())->orWhere('past_user_id', Auth::id());
            });

            if ($filter && $filter !== "") {
                if ($filter === 'all') {
                    // Already filtered by created_by or past_user_id
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

        // Apply payment status filter before pagination
        if ($paymentStatus && $paymentStatus !== "all") {
            $query->where('payment_status', $paymentStatus);
        }

        // Apply sorting with the new requirements
        switch ($sortBy) {
            case 'name':
                // Sort by customer.name
                $query->join('customers', 'bookings.customer_id', '=', 'customers.id')
                    ->orderBy('customers.name', $sortDirection)
                    ->select('bookings.*'); // Important to select only booking fields to avoid column ambiguity

                break;
            case 'booking_date':
                $query->orderBy('booking_date', $sortDirection);

                break;
            case 'amount':
                // Sort by grand_total numerically (cast to decimal)
                $query->orderByRaw("CAST(grand_total AS DECIMAL(10,2)) {$sortDirection}");

                break;
            default:
                // Default sort by created_at
                $query->orderBy('created_at', $sortDirection);
        }

        $data = $query->paginate($limit);

        return $this->success(BookingResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Booking List');
    }

    public function store(BookingRequest $request)
    {
        try {
            $booking = BookingManager::createBookingWithReservation($request);

            return $this->success(new BookingResource($booking), 'Booking created successfully');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $find = Booking::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new BookingResource($find), 'Booking Detail');
    }

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
                'user_id' => $request->user_id ?? $find->user_id,
                'is_past_info' => $request->is_past_info ?? $find->is_past_info,
                'past_user_id' => $request->past_user_id ?? $find->past_user_id,
                'past_crm_id' => $request->past_crm_id ?? $find->past_crm_id,
                'sold_from' => $request->sold_from ?? $find->sold_from,
                'payment_method' => $request->payment_method ?? $find->payment_method,
                'payment_status' => $request->payment_status ?? $find->payment_status,
                'payment_currency' => $request->payment_currency ?? $find->payment_currency,
                'booking_date' => $request->booking_date ?? $find->booking_date,
                'bank_name' => $request->bank_name ?? $find->bank_name,
                'transfer_code' => $request->transfer_code ?? $find->transfer_code,
                'money_exchange_rate' => $request->money_exchange_rate ?? $find->money_exchange_rate,
                'comment' => $request->comment ?? $find->comment,
                'sub_total' => $request->sub_total ?? $find->sub_total,
                'grand_total' => $request->grand_total ?? $find->grand_total,
                'exclude_amount' => $request->exclude_amount ?? $find->exclude_amount,
                'deposit' => $request->deposit ?? $find->deposit,
                'balance_due' => $request->balance_due ?? $find->balance_due,
                'balance_due_date' => $request->balance_due_date ?? $find->balance_due_date,
                'discount' => $request->discount ?? $find->discount,
                'reservation_status' => 'awaiting',
                'payment_notes' => $request->payment_notes,
                'is_inclusive' => $request->is_inclusive ? $request->is_inclusive : $find->is_inclusive,
                'inclusive_name' => $request->inclusive_name ?? $find->inclusive_name,
                'inclusive_description' => $request->inclusive_description ?? $find->inclusive_description,
                'inclusive_quantity' => $request->inclusive_quantity ?? $find->inclusive_quantity,
                'inclusive_rate' => $request->inclusive_rate ?? $find->inclusive_rate,
                'inclusive_start_date' => $request->inclusive_start_date ?? $find->inclusive_start_date,
                'inclusive_end_date' => $request->inclusive_end_date ?? $find->inclusive_end_date,
            ];

            $find->update($data);

            if ($request->receipt_image) {
                foreach ($request->receipt_image as $receipt) {
                    $image = $receipt['file'];
                    $amount = $receipt['amount'];
                    $bank_name = $receipt['bank_name'];
                    $date = $receipt['date'];
                    $is_corporate = $receipt['is_corporate'];
                    $note = $receipt['note'];
                    $sender = $receipt['sender'];
                    $reciever = $receipt['reciever'];
                    $interact_bank = $receipt['interact_bank'];
                    $currency = $receipt['currency'];

                    $fileData = $this->uploads($image, 'images/');

                    BookingReceipt::create([
                        'booking_id' => $find->id,
                        'image' => $fileData['fileName'],
                        'amount' => $amount,
                        'bank_name' => $bank_name,
                        'date' => $date,
                        'is_corporate' => $is_corporate,
                        'note' => $note,
                        'sender' => $sender,
                        'reciever' => $reciever,
                        'interact_bank' => $interact_bank ?? 'personal',
                        'currency' => $currency ?? 'THB',
                    ]);
                }
            }

            if ($request->items) {
                $booking_item_ids = $find->items()->pluck('id')->toArray();
                $request_item_ids = collect($request->items)
                    ->pluck('reservation_id')
                    ->filter(function ($value) {
                        return $value && $value !== 'undefined' && $value !== 'null';
                    })
                    ->toArray();

                $delete_item_ids = array_diff($booking_item_ids, $request_item_ids);

                // Delete items that are no longer in the request
                foreach ($delete_item_ids as $delete_item) {
                    $d_item = BookingItem::find($delete_item);

                    if ($d_item) {
                        if ($d_item->receipt_image) {
                            Storage::delete('images/' . $d_item->receipt_image);
                        }

                        if ($d_item->confirmation_letter) {
                            Storage::delete('files/' . $d_item->confirmation_letter);
                        }

                        $d_item->delete();
                    }
                }

                $booking_items = collect($request->items)->whereNotNull('product_type')->toArray();

                foreach ($booking_items as $key => $item) {
                    $data = [
                        'booking_id' => $find->id,
                        'crm_id' => $find->crm_id . '_' . str_pad($key + 1, 3, '0', STR_PAD_LEFT),
                        'product_type' => $item['product_type'],
                        'room_number' => $item['room_number'] ?? null,
                        'product_id' => $item['product_id'],
                        // Save these fields directly without isset check
                        'car_id' => $item['car_id'] ?? null,
                        'room_id' => $item['room_id'] ?? null,
                        'ticket_id' => $item['ticket_id'] ?? null,
                        'variation_id' => $item['variation_id'] ?? null,
                        'service_date' => $item['service_date'] ?? null,
                        'quantity' => $item['quantity'] ?? null,
                        'total_guest' => $item['total_guest'] ?? null,
                        'days' => $item['days'] ?? null,
                        'special_request' => $item['special_request'] ?? null,
                        'route_plan' => $item['route_plan'] ?? null,
                        'pickup_location' => $item['pickup_location'] ?? null,
                        'pickup_time' => $item['pickup_time'] ?? null,
                        'dropoff_location' => $item['dropoff_location'] ?? null,
                        'duration' => $item['duration'] ?? null,
                        'selling_price' => $item['selling_price'] ?? null,
                        'total_cost_price' => $item['total_cost_price'] ?? null,
                        'amount' => $item['amount'] ?? null,
                        'discount' => $item['discount'] ?? null,
                        'cost_price' => $item['cost_price'] ?? null,
                        'payment_method' => $item['payment_method'] ?? null,
                        'payment_status' => $item['payment_status'] ?? 'not_paid',
                        'exchange_rate' => $item['exchange_rate'] ?? null,
                        'comment' => $item['comment'] ?? null,
                        'checkin_date' => $item['checkin_date'] ?? null,
                        'checkout_date' => $item['checkout_date'] ?? null,
                        'reservation_status' => $item['reservation_status'] ?? "awaiting",
                        'is_inclusive' => (isset($item['reservation_status']) && $item['reservation_status'] == 'undefined') ? "1" : "0",
                        'individual_pricing' => isset($item['individual_pricing']) ? json_encode($item['individual_pricing']) : null,
                        'cancellation' => $item['cancellation'] ?? null,
                        'addon' => isset($item['addon']) ? json_encode($item['addon']) : null,
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

                    // Fixed the check for new vs existing items
                    if (
                        empty($item['reservation_id']) ||
                        $item['reservation_id'] === 'undefined' ||
                        $item['reservation_id'] === 'null'
                    ) {
                        // Create new item
                        BookingItem::create($data);
                    } else {
                        // Update existing item
                        $booking_item = BookingItem::find($item['reservation_id']);
                        if ($booking_item) {
                            $booking_item->update($data);
                        }
                    }
                }
            }

            if ($find->is_inclusive) {
                $booking_item_total = $find->items->where('product_type', '<>', InclusiveProduct::class)->sum('amount');
                $inclusive_profit = $find->grand_total - $booking_item_total;

                $inclusive_item = $find->items()->where('product_type', InclusiveProduct::class)->first();

                if ($inclusive_item) {
                    $inclusive_item->update([
                        'amount' => $inclusive_profit,
                    ]);
                }
            }

            if ($find->wasChanged('deposit')) {
                dispatch(new SendSaleDepositUpdateEmailJob($find));
            }

            // Persist booking item groups
            PersistBookingItemGroupJob::dispatch($find);

            DB::commit();

            if (Auth::user()->role === 'super_admin' && $request->required_archive) {
                ArchiveSaleJob::dispatch($find);
            }

            // Update booking dates
            UpdateBookingDatesJob::dispatch($find->id);

            return $this->success(new BookingResource($find), 'Successfully updated');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function destroy(string $id)
    {
        $find = Booking::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        Order::where('booking_id', $id)->update([
            'order_status' => 'cancelled',
            'booking_id' => null
        ]);

        foreach ($find->items as $item) {
            if ($item->receipt_image) {
                Storage::delete('images/' . $item->receipt_image);
            }
        }

        $find->items()->delete();

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function printReceipt(Request $request, string $id)
    {
        if ($request->query('paid') && $request->query('paid') === 1) {
            $booking = Booking::query()
                ->where('id', $id)
                ->with(['customer', 'items' => function ($q) {
                    $q->where('payment_status', 'fully_paid')
                        ->where('is_inclusive', '0');
                }, 'createdBy'])
                ->first();
        } else {
            $booking = Booking::query()
                ->where('id', $id)
                ->with(['customer', 'items' => function ($q) {
                    $q->where('is_inclusive', '0');
                }, 'createdBy'])
                ->first();
        }

        $data = $booking;
        $data->sub_total = $data->acsr_sub_total;
        $data->grand_total = $data->acsr_grand_total;

        $pdf_view = 'pdf.booking_receipt';

        if ($booking->is_inclusive) {
            $pdf_view = 'pdf.inclusive_booking_receipt';
        }

        $pdf = Pdf::setOption([
            'fontDir' => public_path('/fonts')
        ])->loadView($pdf_view, ['data' => $data]);

        return $pdf->stream();
    }

    public function deleteReceipt($id)
    {
        $find = BookingReceipt::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        Storage::delete('images/' . $find->image);
        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function deleteBookingConfirmLetter($id)
    {
        try {
            $find = ReservationBookingConfirmLetter::find($id);

            if (!$find) {
                return $this->error(null, 'Data not found', 404);
            }

            Storage::delete('images/' . $find->image);

            $find->delete();

            return $this->success(null, 'Successfully deleted');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }
}
