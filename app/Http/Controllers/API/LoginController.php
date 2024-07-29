<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8'
        ]);

        if (!Auth::guard('user')->attempt($request->only('email', 'password'))) {
            return failedMessage('Credentials do not match');
        }

        $user = Auth::guard('user')->user();

        $token = $user->createToken($user->name . '-AuthToken')->plainTextToken;

        return success([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('user')->user();

        auth()->user()->tokens()->delete();

        return successMessage('Successfully Logout');
    }
}
