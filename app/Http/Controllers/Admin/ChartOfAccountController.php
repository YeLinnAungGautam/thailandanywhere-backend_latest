<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChartOfAccountResource;
use App\Models\AccountClass;
use App\Models\AccountHead;
use App\Models\BookingItem;
use App\Models\ChartOfAccount;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChartOfAccountController extends Controller
{
    use HttpResponses;
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $month = $request->query('month', date('Y-m'));  // Default to current month if not provided

        $query = ChartOfAccount::query()->with('accountClass', 'accountHead');

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $data = $query->paginate($limit);
        $collection = ChartOfAccountResource::collection($data);

        // Process and add total amounts for specific connections
        $collection->each(function ($item) use ($month) {
            // Handle price connection_detail
            if ($item->connection_detail === 'price') {
                $totalAmount = 0;
                $totalUnverifyAmount = 0;

                // For VanTour connection
                if ($item->connection === 'vantour') {
                    $totalAmount = $this->calculateTotalForProductType('App\\Models\\PrivateVanTour', $month, 'fully_paid');
                    $totalUnverifyAmount = $this->calculateTotalForProductType('App\\Models\\PrivateVanTour', $month, 'not_fully_paid');
                }

                // For Hotel connection
                else if ($item->connection === 'hotel') {
                    $totalAmount = $this->calculateTotalForProductType('App\\Models\\Hotel', $month, 'fully_paid');
                    $totalUnverifyAmount = $this->calculateTotalForProductType('App\\Models\\Hotel', $month, 'not_fully_paid');
                }

                // For Ticket connection
                else if ($item->connection === 'ticket') {
                    $totalAmount = $this->calculateTotalForProductType('App\\Models\\EntranceTicket', $month, 'fully_paid');
                    $totalUnverifyAmount = $this->calculateTotalForProductType('App\\Models\\EntranceTicket', $month, 'not_fully_paid');
                }

                $item->total_amount = $totalAmount;
                $item->total_unverify_amount = $totalUnverifyAmount;
            }
            // Handle expense connection_detail
            else if ($item->connection_detail === 'expense') {
                $verifiedCostPrice = 0;
                $unverifiedCostPrice = 0;

                // For VanTour connection
                if ($item->connection === 'vantour') {
                    $verifiedCostPrice = $this->calculateTotalCostPrice('App\\Models\\PrivateVanTour', $month, 'fully_paid');
                    $unverifiedCostPrice = $this->calculateTotalCostPrice('App\\Models\\PrivateVanTour', $month, 'not_fully_paid');
                }

                // For Hotel connection
                else if ($item->connection === 'hotel') {
                    $verifiedCostPrice = $this->calculateTotalCostPrice('App\\Models\\Hotel', $month, 'fully_paid');
                    $unverifiedCostPrice = $this->calculateTotalCostPrice('App\\Models\\Hotel', $month, 'not_fully_paid');
                }

                // For Ticket connection
                else if ($item->connection === 'ticket') {
                    $verifiedCostPrice = $this->calculateTotalCostPrice('App\\Models\\EntranceTicket', $month, 'fully_paid');
                    $unverifiedCostPrice = $this->calculateTotalCostPrice('App\\Models\\EntranceTicket', $month, 'not_fully_paid');
                }

                $item->total_cost_price = $verifiedCostPrice;
                $item->total_unverify_cost_price = $unverifiedCostPrice;
            }
        });

        return $this->success(
            $collection->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])->response()->getData(),
            'Account Class List'
        );
    }

    /**
     * Calculate total amount for a specific product type in the given month based on booking payment status
     *
     * @param string $productType The fully qualified class name of the product type
     * @param string $month The month in YYYY-MM format
     * @param string $paymentStatus Booking payment status to filter by ('fully_paid' or 'not_fully_paid')
     * @return float The total amount
     */
    private function calculateTotalForProductType($productType, $month, $paymentStatus)
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $query = BookingItem::where('product_type', $productType)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('amount')
            ->whereHas('booking', function ($query) use ($paymentStatus) {
                // Only include booking items whose related booking is not inclusive
                $query->where('is_inclusive', '!=', 1);

                // Filter by booking payment status
                if ($paymentStatus === 'fully_paid') {
                    $query->where('payment_status', 'fully_paid');
                } else {
                    $query->where('payment_status', '!=', 'fully_paid');
                }
            });

        return $query->sum('amount');
    }

    /**
     * Calculate total cost price for a specific product type in the given month based on booking payment status
     *
     * @param string $productType The fully qualified class name of the product type
     * @param string $month The month in YYYY-MM format
     * @param string $paymentStatus Booking payment status to filter by ('fully_paid' or 'not_fully_paid')
     * @return float The total cost price
     */
    private function calculateTotalCostPrice($productType, $month, $paymentStatus)
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $query = BookingItem::where('product_type', $productType)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('total_cost_price')
            ->whereHas('booking', function ($query) use ($paymentStatus) {
                // Only include booking items whose related booking is not inclusive
                $query->where('is_inclusive', '!=', 1);

                // Filter by booking payment status
                if ($paymentStatus === 'fully_paid') {
                    $query->where('payment_status', 'fully_paid');
                } else {
                    $query->where('payment_status', '!=', 'fully_paid');
                }
            });

        return $query->sum('total_cost_price');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_code' => 'required|string|max:255|unique:chart_of_accounts,account_code',
            'account_name' => 'required|string|max:255',
            'account_class_id' => 'required|exists:account_classes,id',
            'account_head_id' => 'required|exists:account_heads,id',
            'product_type' => 'nullable|string|max:255',
            'connection' => 'nullable|string|max:255',
            'connection_detail' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Validation error');
        }

        // Verify that the account class and head exist
        $accountClass = AccountClass::find($request->account_class_id);
        $accountHead = AccountHead::find($request->account_head_id);

        if (!$accountClass || !$accountHead) {
            return $this->error(null, 'Account class or head not found');
        }

        $account = ChartOfAccount::create([
            'account_code' => $request->account_code,
            'account_name' => $request->account_name,
            'account_class_id' => $request->account_class_id,
            'account_head_id' => $request->account_head_id,
            'product_type' => $request->product_type,
            'connection' => $request->connection,
            'connection_detail' => $request->connection_detail,
        ]);

        return $this->success(new ChartOfAccountResource($account), 'Successfully created');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $account = ChartOfAccount::find($id);

        if (!$account) {
            return $this->error(null, 'Account not found', 404);
        }

        $account->update([
            'account_code' => $request->account_code ?? $account->account_code,
            'account_name' => $request->account_name ?? $account->account_name,
            'account_class_id' => $request->account_class_id ?? $account->account_class_id,
            'account_head_id' => $request->account_head_id ?? $account->account_head_id,
            'product_type' => $request->product_type ?? $account->product_type,
            'connection' => $request->connection ?? $account->connection,
            'connection_detail' => $request->connection_detail ?? $account->connection_detail,
        ]);

        // Refresh account with relationships
        $account = ChartOfAccount::with(['accountClass', 'accountHead'])->find($id);

        return $this->success(new ChartOfAccountResource($account), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $account = ChartOfAccount::find($id);

        if (!$account) {
            return $this->error(null, 'Account not found', 404);
        }

        $account->delete();

        return $this->success(null, 'Account deleted successfully');
    }
}
