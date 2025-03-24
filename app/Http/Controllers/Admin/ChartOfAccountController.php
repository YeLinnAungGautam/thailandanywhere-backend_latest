<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChartOfAccountResource;
use App\Models\AccountClass;
use App\Models\AccountHead;
use App\Models\ChartOfAccount;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChartOfAccountController extends Controller
{
    use HttpResponses;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');

        $query = ChartOfAccount::query()->with('accountClass', 'accountHead');

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $data = $query->paginate($limit);
        return $this->success(ChartOfAccountResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Account Class List');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_code' => 'required|string|max:255|unique:chart_of_accounts',
            'account_name' => 'required|string|max:255',
            'account_class_id' => 'required|exists:account_classes,id',
            'account_head_id' => 'required|exists:account_heads,id',
            'product_type' => 'nullable|string|max:255',
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

        $validator = Validator::make($request->all(), [
            'account_code' => 'required|string|max:255|unique:chart_of_accounts,account_code,' . $id,
            'account_name' => 'required|string|max:255',
            'account_class_id' => 'required|exists:account_classes,id',
            'account_head_id' => 'required|exists:account_heads,id',
            'product_type' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Validation error');
        }

        $account->update([
            'account_code' => $request->account_code,
            'account_name' => $request->account_name,
            'account_class_id' => $request->account_class_id,
            'account_head_id' => $request->account_head_id,
            'product_type' => $request->product_type,
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
