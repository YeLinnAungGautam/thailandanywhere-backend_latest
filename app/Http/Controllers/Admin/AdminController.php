<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Services\SaleReportService;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');

        $query = Admin::query()
            ->with('subsidiaries');

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');
        $data = $query->paginate($limit);

        return $this->success(AdminResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Admin List');

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $request->validate([
            'name' => ['required', 'string', 'max:225'],
            'email' => ['required', 'email', 'max:225', Rule::unique('admins', 'email')],
            'password' => ['required', 'string', 'confirmed', 'max:225'],
            'target_amount' => ['nullable', 'integer']
        ]);


        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'target_amount' => $request->target_amount
        ]);

        return $this->success($admin, 'Successfully created', 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Admin $admin)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Admin $admin)
    {

        $request->validate([
            'name' => ['string', 'max:225'],
            'email' => ['email', 'max:225', Rule::unique('admins', 'email')->ignore($admin)],
            'password' => ['string', 'confirmed', 'max:225'],
            'target_amount' => ['nullable', 'integer']
        ]);

        $admin->name = $request->name ?? $admin->name;
        $admin->email = $request->email ?? $admin->email;
        $admin->password = $request->password ? Hash::make($request->password) : $admin->password;
        $admin->role = $request->role ?? $admin->role;
        $admin->target_amount = $request->target_amount ?? $admin->target_amount;
        $admin->update();

        return $this->success($admin, 'Successfully updated', 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Admin $admin)
    {
        $admin->delete();

        return $this->success(null, 'Successfully deleted', 200);
    }

    /**
     * Get current sale ranking of the auth user
     */
    public function getCurrentSaleRank()
    {
        return $this->success(['rank' => SaleReportService::getSaleRank(auth('sanctum')->user())], 200);
    }
}
