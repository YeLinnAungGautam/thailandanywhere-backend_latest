<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItemResource;
use App\Http\Resources\ChartOfAccount\BookingItemResource as ChartOfAccountBookingItemResource;
use App\Http\Resources\ChartOfAccountResource;
use App\Models\AccountClass;
use App\Models\AccountHead;
use App\Models\BookingItem;
use App\Models\ChartOfAccount;
use App\Services\ChartOfAccountCalculationService;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChartOfAccountController extends Controller
{
    use HttpResponses;

    protected $calculationService;

    public function __construct(ChartOfAccountCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $searchCode = $request->query('search_code');
        $month = $request->query('month', date('Y-m'));  // Default to current month if not provided

        $query = ChartOfAccount::query()->with('accountClass', 'accountHead');

        if ($search) {
            $query->where('account_name', 'LIKE', "%{$search}%");
        }

        if ($searchCode) {
            $query->where('account_code', 'LIKE', "%{$searchCode}%");
        }

        $query->orderByRaw(
            "LENGTH(account_code), account_code"
        );

        $data = $query->paginate($limit);
        $collection = ChartOfAccountResource::collection($data);

        // Process and add total amounts using service
        $collection->each(function ($item) use ($month) {
            // Handle specific account codes with overdue and booking calculations
            if (in_array($item->account_code, ['1-3000-01', '1-3000-02', '1-3000-03'])) {
                $item = $this->calculationService->calculateAccountCodeTotals($item, $month);
            }
            // Handle existing price connection_detail
            elseif ($item->connection_detail == 'price') {
                $item = $this->calculationService->calculatePriceConnectionTotals($item, $month);
            }
            // Handle existing expense connection_detail
            elseif ($item->connection_detail === 'expense') {
                $item = $this->calculationService->calculateExpenseConnectionTotals($item, $month);
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
     * Calculate total for a specific product type in the given month based on booking payment status and verify status
     * Keep this method for backward compatibility and existing functionality
     *
     * @param  string      $productType   The fully qualified class name of the product type
     * @param  string      $month         The month in YYYY-MM format
     * @param  string      $field         The field to sum ('amount' or 'total_cost_price')
     * @param  string      $paymentStatus Booking payment status to filter by ('fully_paid')
     * @param  string|null $verifyStatus  Booking verify status to filter by (null, 'verified', 'unverified', 'pending')
     * @return float       The total amount
     */

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

    public function balanceDueOver(Request $request)
    {
        $month = $request->query('month', date('Y-m'));
        $limit = $request->query('limit', 10);
        $productType = $request->query('product_type');

        if (!$productType) {
            return $this->error('Product type is required.', 400);
        }

        // Fetch the results (not the query)
        $data = $this->calculationService->getItemOverBalanceDue($productType, $month)->paginate($limit);

        return $this->success(
            ChartOfAccountBookingItemResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])->response()->getData(),
            'Receiptable checker List'
        );
    }
}
