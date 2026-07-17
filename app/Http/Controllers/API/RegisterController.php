<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Mail\VerifyEmail;
use App\Models\User;
use App\Traits\HttpResponses;
use App\Traits\HandlesReactivation;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Mail;
use function Laravel\Prompts\error;

class RegisterController extends Controller
{
    use HttpResponses, HandlesReactivation;

    public function register(RegisterRequest $request)
    {
        try {
            // Include trashed so deleted accounts are detected here too
            $existingUser = User::withTrashed()->where('email', $request->email)->first();

            if ($existingUser) {

                // Previously deleted account: send reactivation email instead of erroring
                if ($existingUser->trashed()) {
                    $this->sendReactivationEmail($existingUser);

                    return success([
                        'reactivation_required' => true,
                        'email' => $existingUser->email,
                    ], 'This email was previously registered and deleted. We\'ve sent a reactivation email — please set a new password to restore your account.');
                }

                // If the user exists but is not active (not verified)
                if (!$existingUser->is_active) {
                    $code = sprintf('%06d', mt_rand(0, 999999));

                    $existingUser->email_verification_token = $code;
                    $existingUser->save();

                    Mail::to($existingUser->email)->queue(new VerifyEmail($existingUser));

                    return error('This email is already registered but not verified. A new verification code has been sent to your email.');
                }

                // If user exists and is active
                return error('This email is already registered. Please login instead.');
            }

            // If email doesn't exist at all, create new user
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

            Mail::to($user->email)->queue(new VerifyEmail($user));

            return success($user, 'User registered successfully. Please check your email to verify your account.');
        } catch (Exception $e) {
            return error('An error occurred during registration: ' . $e->getMessage());
        }
    }

    // For POST request
    public function resendVerificationEmail(Request $request)
    {
        try {
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

            $code = sprintf('%06d', mt_rand(0, 999999));

            $user->email_verification_token = $code;
            $user->email_verified_at = null;
            $user->is_active = false;
            $user->save();

            Mail::to($user->email)->queue(new VerifyEmail($user));

            return success(null, 'Verification email sent successfully.');
        } catch (Exception $e) {
            return error('An error occurred while resending the verification email: ' . $e->getMessage());
        }
    }

    public function verifyEmail(Request $request)
    {
        try {
            $request->validate([
                'verification_code' => 'required|numeric|digits:6',
                'email' => 'required|email'
            ]);

            $code = $request->verification_code;
            $email = $request->email;

            $user = User::where('email', $email)
                ->where('email_verification_token', $code)
                ->first();

            if (!$user) {
                throw new Exception('Invalid verification code or email.');
            }

            $user->email_verification_token = null;
            $user->email_verified_at = Carbon::now();
            $user->is_active = true;
            $user->save();

            $token = $user->createToken($user->name . '-AuthToken')->plainTextToken;

            return success([
                'user' => $user,
                'token' => $token
            ], 'Your email has been verified successfully. You can now login.');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }
}
