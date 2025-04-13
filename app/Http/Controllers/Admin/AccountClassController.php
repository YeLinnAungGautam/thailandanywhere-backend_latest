<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountClassResource;
use App\Models\AccountClass;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class AccountClassController extends Controller
{
    use HttpResponses;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');

        $query = AccountClass::query();

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $data = $query->paginate($limit);
        return $this->success(AccountClassResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Account Class List');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:225',
            'code' => 'required|string|max:225|unique:account_classes,code',
            'account_head_id' => 'required|exists:account_heads,id',
        ]);

        $data = [
            'name' => $request->name,
            'code' => $request->code,
            'account_head_id' => $request->account_head_id,
        ];

        $save = AccountClass::create($data);
        return $this->success(new AccountClassResource($save), 'Successfully created');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $find = AccountClass::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $data = [
            'name' => $request->name ?? $find->name,
            'code' => $request->code?? $find->code,
            'account_head_id' => $request->account_head_id?? $find->account_head_id,
        ];


        $find->update($data);

        return $this->success(new AccountClassResource($find), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $find = AccountClass::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->delete();
        return $this->success(null, 'Successfully deleted');
    }
}
