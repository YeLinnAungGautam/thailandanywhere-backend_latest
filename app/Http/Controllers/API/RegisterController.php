<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Mail\VerifyEmail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use function Laravel\Prompts\error;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request)
    {
        // create 6-digit verification code
        $code = sprintf('%06d', mt_rand(0, 999999));

        $user = User::create([
            'name' => $request->first_name . ' ' . $request->last_name,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'dob' => $request->dob,
            'email_verification_token' => $code,
            'email_verified_at' => null,
            'is_active' => false,
        ]);

        // send verification email
        Mail::to($user->email)->queue(new VerifyEmail($user));

        return success($user, 'User registered successfully. Please check your email to verify your account.');
    }

    // For POST request
    public function resendVerificationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = $request->email;

        $user = User::where('email', $email)->first();

        if (!$user) {
            return error('User not found!');
        }

        if ($user->email_verified_at) {
            return error('Email already verified!');
        }

        // Generate new 6-digit verification code
        $code = sprintf('%06d', mt_rand(0, 999999));

        $user->email_verification_token = $code;
        $user->email_verified_at = null;
        $user->is_active = false; // Deactivate the user before sending the verification email
        $user->save();

        // send verification email
        Mail::to($user->email)->queue(new VerifyEmail($user));

        return success(null, 'Verification email sent successfully.');
    }

    public function verifyEmail(Request $request)
    {
        $code = $request->code;
        $user = User::where('email_verification_token', $code)->first();

        if (!$user) {
            return error('Invalid verification code!');
        }

        $user->email_verification_token = null;
        $user->email_verified_at = Carbon::now();
        $user->is_active = true; // Activate the user after email verification
        $user->save();

        $token = $user->createToken($user->name . '-AuthToken')->plainTextToken;

        return success([
            'user' => $user,
            'token' => $token
        ], 'Your email has been verified successfully. You can now login.');
    }
}
