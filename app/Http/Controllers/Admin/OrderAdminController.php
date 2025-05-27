<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\OrderResource;
use App\Jobs\ArchiveSaleJob;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingReceipt;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderAdminController extends Controller
{
    use HttpResponses, ImageManager;
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);

        // Get filter parameters
        $status = $request->query('status');
        $orderNumber = $request->query('order_number');
        $adminId = $request->query('admin_id');
        $orderDatetime = $request->query('order_datetime');
        $balanceDueDate = $request->query('balance_due_date');

        // Initialize query with relationships
        $query = Order::with(['items', 'customer', 'admin', 'payments']);

        // Apply filters if provided
        if ($status) {
            $query->where('order_status', $status);
        }

        if ($orderNumber) {
            $query->where('order_number', 'like', "%{$orderNumber}%");
        }

        if ($adminId) {
            $query->where('admin_id', $adminId);
        }

        // Date range filtering for order_datetime
        if ($orderDatetime) {
            // Check if it's a range or single date
            if (strpos($orderDatetime, ',') !== false) {
                // It's a range
                list($startDate, $endDate) = explode(',', $orderDatetime);
                $query->whereBetween('created_at', [
                    date('Y-m-d 00:00:00', strtotime(trim($startDate))),
                    date('Y-m-d 23:59:59', strtotime(trim($endDate)))
                ]);
            } else {
                // It's a single date
                $query->whereDate('created_at', date('Y-m-d', strtotime($orderDatetime)));
            }
        }

        // Date range filtering for balance_due_date
        if ($balanceDueDate) {
            // Check if it's a range or single date
            if (strpos($balanceDueDate, ',') !== false) {
                // It's a range
                list($startDate, $endDate) = explode(',', $balanceDueDate);
                $query->whereBetween('balance_due_date', [
                    date('Y-m-d 00:00:00', strtotime(trim($startDate))),
                    date('Y-m-d 23:59:59', strtotime(trim($endDate)))
                ]);
            } else {
                // It's a single date
                $query->whereDate('balance_due_date', date('Y-m-d', strtotime($balanceDueDate)));
            }
        }

        // Get paginated results
        $orders = $query->orderBy('created_at', 'desc')->paginate($limit);

        // Return formatted response
        return $this->success(
            OrderResource::collection($orders)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($orders->total() / $orders->perPage()),
                ],
            ])
            ->response()
            ->getData(),
            'Booking List'
        );
    }

    public function addPayment (Request $request, $id){
        // return response()->json(['messge' => $request->all()]);
        $order = Order::find($id);

        if(!$order){
            return $this->error('ဤအော်ဒါကို မရှိပါ', 404);
        }

        try {
            $order_payment = [
                'order_id' => $order->id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => Carbon::now(),
                'status' => $request->status,
                'approved_by' => Auth()->user()->id,
            ];

            if($request->hasFile('payment_slip')) {
                $file = $this->uploads($request->file('payment_slip'), 'order_payments/');
                $order_payment['payment_slip'] = $file['fileName'];
            }

            $order_create = OrderPayment::create($order_payment);

            $order->update([
                'order_status' => 'processing',
                'deposit_amount' => $order->deposit_amount + $order_payment['amount'],
                'admin_id' => Auth()->user()->id
            ]);

            return $this->success(new OrderResource($order), 'အော်ဒါကို ရှင်းမှာပါ');

        } catch (\Exception $e) {
            //throw $th;
            return $this->error(null, $e->getMessage());
        }
    }

    public function changeOrderToBooking (Request $request, $id)
        {
            DB::beginTransaction();

            try {
                // Find the order
                $order = Order::with(['items', 'customer'])->findOrFail($id);

                // Check if order is already approved
                if ($order->order_status === 'sale_convert') {
                    return $this->error(null, 'Order is already approved');
                }

                // Convert order to booking
                $booking = $this->convertOrderToBooking($order, $request);

                DB::table('orders')
                ->where('id', $order->id)
                ->update([
                    'order_status' => 'sale_convert',
                    'booking_id' => $booking->id,
                    'updated_at' => now()
                ]);

                DB::commit();

                // Dispatch archive job
                ArchiveSaleJob::dispatch($booking);

                return $this->success(
                    new BookingResource($booking),
                    'Order successfully approved and converted to booking'
                );

            } catch (Exception $e) {
                DB::rollBack();
                // Log::error('Order approval error: ' . $e->getMessage());

                return $this->error(null, $e->getMessage());
            }
        }

    private function convertOrderToBooking (Order $order, Request $request){
        $bookingData = [
            'customer_id' => $order->customer_id,
            'user_id' => $order->user_id, // Current admin user
            'sold_from' => $order->sold_from,
            'payment_method' => $request->payment_method ?? 'bank_transfer',
            'payment_status' => $request->payment_status ? 'partially_paid' : 'not_paid',
            'payment_currency' => $request->payment_currency ?? 'THB',
            'booking_date' => Carbon::now(),
            'bank_name' => $request->bank_name ?? 'bank',
            'transfer_code' => $request->transfer_code ?? null,
            'money_exchange_rate' => $request->money_exchange_rate ?? 0,
            'sub_total' => $order->sub_total,
            'grand_total' => $order->grand_total,
            'exclude_amount' => $request->exclude_amount ?? 0,
            'deposit' => $order->deposit_amount,
            'balance_due' => $order->grand_total - $order->deposit_amount,
            'balance_due_date' => $order->balance_due_date,
            'discount' => $order->discount,
            'comment' => $order->comment,
            'created_by' => Auth::id(),
            'reservation_status' => "pending",
        ];

        $booking = Booking::create($bookingData);

        if ($request->has('receipt_image')) {
            foreach ($request->receipt_image as $receipt) {
                $image = $receipt['file'];
                $amount = $receipt['amount'];

                $fileData = $this->uploads($image, 'images/');

                BookingReceipt::create([
                    'booking_id' => $booking->id,
                    'image' => $fileData['fileName'],
                    'amount' => $amount
                ]);
            }
        }

        $this->convertOrderItemsToBookingItems($order, $booking);

        return $booking;
    }

    private function convertOrderItemsToBookingItems (Order $order, $booking){
        foreach ($order->items as $key => $item) {
            $individualPricing = null;
            if ($item->individual_pricing) {
                // For booking_items (LONGTEXT), we need to ensure it's a JSON string
                $individualPricing = is_array($item->individual_pricing) ?
                    json_encode($item->individual_pricing) :
                    $item->individual_pricing;
            }
            $data = [
                'booking_id' => $booking->id,
                'crm_id' => $booking->crm_id . '_' . str_pad($key + 1, 3, '0', STR_PAD_LEFT),
                'product_type' => $item->product_type,
                'product_id' => $item->product_id,
                'car_id' => $item->car_id ?? null,
                'room_id' => $item->room_id ?? null,
                'variation_id' => $item->variation_id ?? null,
                'service_date' => $item->service_date,
                'checkin_date' => $item->product_type == 'App\\Models\\Hotel' ? $item->service_date : null,
                'quantity' => $item->quantity,
                'duration' => $item->duration ?? null,
                'selling_price' => $item->selling_price,
                'cost_price' => $item->cost_price,
                'total_cost_price' => $item->total_cost_price,
                'payment_method' => $item->payment_method,
                'payment_status' => $item->payment_status ?? null,
                'exchange_rate' => $item->exchange_rate ?? null,
                'comment' => $item->comment ?? null,
                'amount' => $item->total_selling_price,
                'discount' => $item->discount,
                'special_request' => $item->special_request ?? null,
                'route_plan' => $item->route_plan ?? null,
                'pickup_location' => $item->pickup_location ?? null,
                'pickup_time' => $item->pickup_time ?? null,
                'dropoff_location' => $item->dropoff_location ?? null,
                'checkin_date' => $item->checkin_date ?? null,
                'checkout_date' => $item->checkout_date ?? null,
                'reservation_status' => 'pending',
                'individual_pricing' => $individualPricing,
            ];

            BookingItem::create($data);
        }
    }



    public function changeStatus(Request $request, $id){
        $order = Order::findOrFail($id);
        $order->update([
            'order_status' => $request->status
        ]);
        return $this->success(new OrderResource($order), 'Order status changed successfully');
    }

    public function deleteOrder($id){
        $order = Order::findOrFail($id);
        $order->delete();
        return $this->success(null, 'Order deleted successfully');
    }
}
