<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    use HttpResponses;

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::guard('admin')->attempt($request->only('email', 'password'))) {
            return $this->error('', 'Credentials do not match', 401);
        }

        $admin = Auth::guard('admin')->user();

        // $admin->tokens()->delete();

        $abilities = [
            'admin'
        ];

        if ($admin->role === 'super_admin') {
            $abilities[] = '*';
        }

        if ($admin->role === 'cashier') {
            $abilities[] = 'admin';
        }

        return $this->success([
            'user' => $admin,
            'token' => $admin->createToken('API Token of admin id ' . $admin->id, $abilities)->plainTextToken
        ], 'Successfully Login');
    }

    public function me(Request $request)
    {
        $query = Admin::query();
        $query->where('id', Auth::id());
        $data = $query->first();

        return $this->success([
            'user' => $data,
        ], 'Admin Account Detail');
    }

    public function logout()
    {
        $admin = Auth::user();
        $admin->currentAccessToken()->delete();

        return $this->success(null, 'Successfully Logout');
    }

    public function logoutAll()
    {
        PersonalAccessToken::where('tokenable_type', Admin::class)->delete();

        return $this->success(null, 'Successfully logout for all accounts');
    }
}
