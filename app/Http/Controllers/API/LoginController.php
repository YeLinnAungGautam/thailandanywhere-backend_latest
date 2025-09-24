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
            'password' => 'required'
        ]);

        if (!Auth::guard('user')->attempt($request->only('email', 'password'))) {
            return failedMessage('Credentials do not match');
        }

        $user = Auth::guard('user')->user();

        // Check if user is active
        if (!$user->is_active) {
            // Log the user out since we just logged them in with Auth::attempt
            Auth::guard('user')->logout();

            return failedMessage('Your account is not active. Please verify your email or contact administrator.');
        }

        // Check if email is verified (optional, if you're also implementing email verification)
        if ($user->email_verified_at === null) {
            Auth::guard('user')->logout();

            return failedMessage('Please verify your email before logging in.');
        }

        $token = $user->createToken($user->name . '-AuthToken-' . now())->plainTextToken;

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
