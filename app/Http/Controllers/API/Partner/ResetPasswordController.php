<?php
namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordController extends Controller
{
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:partners,email',
            'password' => 'required|confirmed|min:6',
        ]);

        $status = Password::broker('partners')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($partner, $password) {
                $partner->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successful.'])
            : response()->json(['message' => 'Reset failed.'], 400);
    }
}
