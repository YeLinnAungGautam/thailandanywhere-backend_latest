<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReservationTransactionRequest;
use App\Http\Resources\ReservationTransactionResource;
use App\Jobs\MakeExpenseStatusFullyPaidJob;
use App\Models\BookingItem;
use App\Models\BookingItemReservationTransaction;
use App\Models\ReservationTransaction;
use App\Models\Supplier;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReservationTransactionController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $transactions = ReservationTransaction::query()
            ->with('bookingItems', 'vendorable', 'reservationPaymentSlips')
            ->when($request->datetime, fn ($query) => $query->where('datetime', $request->datetime))
            ->paginate($request->limit ?? 20);

        return ReservationTransactionResource::collection($transactions)->additional(['result' => 1, 'message' => 'success']);
    }

    public function store(ReservationTransactionRequest $request)
    {
        try {
            $booking_ids = [];
            foreach($request->crm_ids as $crm_id) {
                $booking_item = BookingItem::where('crm_id', $crm_id)->first();

                if(!$booking_item) {
                    throw new Exception("CRM ID - $crm_id doesn't exists");
                }

                if($booking_item->isFullyPaid()) {
                    throw new Exception("CRM ID - $crm_id is already fully paid");
                }

                $booking_ids[] = $booking_item->id;
            }

            $reservation_transaction = ReservationTransaction::create([
                'datetime' => $request->datetime,
                'vendorable_id' => $request->supplier_id,
                'vendorable_type' => Supplier::class,
                'total_paid' => (int) $request->total_paid,
                'notes' => $request->notes ?? null,
            ]);

            $reservation_transaction->bookingItems()->attach($booking_ids);

            if($request->payment_slips) {
                foreach($request->payment_slips as $payment_slip) {
                    $file_name = uploadFile($payment_slip, 'images/payment_slips/');

                    $reservation_transaction->reservationPaymentSlips()->create(['file' => $file_name]);
                }
            }

            MakeExpenseStatusFullyPaidJob::dispatch($request->crm_ids);

            return $this->success(new ReservationTransactionResource($reservation_transaction), 'Reservation transaction is successfully created.', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    public function deleteTransaction(string $reservation_id, string $transaction_id)
    {
        try {
            $exists = BookingItemReservationTransaction::where('booking_item_id', $reservation_id)->where('reservation_transaction_id', $transaction_id)->exists();

            if(!$exists) {
                throw new Exception("Invalid reservation transaction ID.");
            }

            BookingItemReservationTransaction::where('booking_item_id', $reservation_id)->where('reservation_transaction_id', $transaction_id)->first()->delete();

            return $this->success(null, 'Reservation transaction is successfully deleted.', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }
}
