<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Mail\ResetPasswordEmail;
use App\Services\SessionTracker;
use App\Traits\HandlesReactivation;
use App\Traits\HttpResponses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Exception;


class LoginController extends Controller
{
    use HttpResponses, HandlesReactivation;

    protected $tracker;

    public function __construct(SessionTracker $tracker)
    {
        $this->tracker = $tracker;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::withTrashed()->where('email', $request->email)->first();

        if (!$user) {
            return failedMessage('Credentials do not match');
        }

        // Deleted account: don't check password or auto-restore.
        // Send a reactivation email so the user sets a fresh password.
        if ($user->trashed()) {
            $this->sendReactivationEmail($user);

            return success([
                'reactivation_required' => true,
                'email' => $user->email,
            ], 'This account was deleted. We\'ve sent a reactivation email — please set a new password to restore your account.');
        }

        if (!Hash::check($request->password, $user->password)) {
            return failedMessage('Credentials do not match');
        }

        Auth::guard('user')->login($user);

        // Check if user is active
        if (!$user->is_active) {
            Auth::guard('user')->logout();
            return failedMessage('Your account is not active. Please verify your email or contact administrator.');
        }

        // Check if email is verified
        if ($user->email_verified_at === null) {
            Auth::guard('user')->logout();
            return failedMessage('Please verify your email before logging in.');
        }

        // Link guest session to user
        $sessionHash = $request->input('session_hash');
        if ($sessionHash) {
            $this->tracker->linkSessionToUser($sessionHash, $user->id);
        }

        $token = $user->createToken($user->name . '-AuthToken-' . now())->plainTextToken;

        return success([
            'user' => $user,
            'token' => $token
        ], 'Successfully logged in.');
    }

    public function forgetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $email = $request->email;

            // Include trashed so deleted accounts can be reactivated via this flow too
            $user = User::withTrashed()->where('email', $email)->first();

            // Always return the same success message for security
            // (don't reveal whether the email exists, is active, or is deleted)
            if (!$user) {
                return successMessage('If an account with that email exists, a password reset link has been sent.');
            }

            if ($user->trashed()) {
                $this->sendReactivationEmail($user);
                return successMessage('If an account with that email exists, a password reset link has been sent.');
            }

            // Generate random token
            $token = Str::random(64);

            // Delete old tokens for this email
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            // Store token in database
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            // Send password reset email
            Mail::to($user->email)->queue(new ResetPasswordEmail($user, $token));

            return successMessage('If an account with that email exists, a password reset link has been sent.');

        } catch (Exception $e) {
            return $this->error('An error occurred while processing your request: ' . $e->getMessage());
        }
    }

    /**
     * Verify token for Node.js Chat Server
     */
    public function verifyToken(Request $request)
    {
        try {
            $query = User::query();
            $query->where('id', Auth::id());
            $user = $query->first();

            if (!$user) {
                return $this->error('', 'Invalid token', 401);
            }

            $sessionHash = $request->input('session_hash');
            if ($sessionHash) {
                $this->tracker->linkSessionToUser($sessionHash, $user->id);
            }

            return success([
                'id' => (string) $user->id,
                'type' => 'user',
                'name' => $user->name,
                'email' => $user->email,
                'profile' => $user->profile ?? null,
            ], 'Token verified successfully');

        } catch (Exception $e) {
            return $this->error('', 'Token verification failed: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Verify reset token
     */
    public function verifyResetToken(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email',
            ]);

            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$resetRecord) {
                return $this->error('Invalid or expired password reset token.');
            }

            $tokenCreatedAt = \Carbon\Carbon::parse($resetRecord->created_at);
            if ($tokenCreatedAt->addMinutes(60)->isPast()) {
                DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();
                return $this->error('Password reset token has expired.');
            }

            if (!Hash::check($request->token, $resetRecord->token)) {
                return $this->error('Invalid password reset token.');
            }

            return success(null, 'Token is valid.');

        } catch (Exception $e) {
            return $this->error('An error occurred while verifying token: ' . $e->getMessage());
        }
    }

    /**
     * Reset password (also reactivates a soft-deleted account, if applicable)
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|string|confirmed',
            ]);

            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$resetRecord) {
                return $this->error('Invalid or expired password reset token.');
            }

            $tokenCreatedAt = \Carbon\Carbon::parse($resetRecord->created_at);
            if ($tokenCreatedAt->addMinutes(60)->isPast()) {
                DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();
                return $this->error('Password reset token has expired.');
            }

            if (!Hash::check($request->token, $resetRecord->token)) {
                return $this->error('Invalid password reset token.');
            }

            // Include trashed so a deleted account can be reactivated right here
            $user = User::withTrashed()->where('email', $request->email)->first();

            if (!$user) {
                return $this->error('User not found.');
            }

            $wasRestored = false;
            if ($user->trashed()) {
                $user->restore();
                $user->is_active = true;
                $wasRestored = true;
            }

            $user->password = Hash::make($request->password);
            $user->save();

            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            $message = $wasRestored
                ? 'Your account has been reactivated and your password has been reset. You can now login.'
                : 'Password has been reset successfully.';

            return success(null, $message);

        } catch (Exception $e) {
            return $this->error('An error occurred while resetting password: ' . $e->getMessage());
        }
    }

    public function logout(Request $request)
    {
        auth()->user()->tokens()->delete();

        return successMessage('Successfully Logout');
    }

    public function setPassword(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required|string|confirmed',
            ]);

            $user = Auth::user();

            if (!$user) {
                return $this->error('', 'Unauthenticated.', 401);
            }

            $user->password = Hash::make($request->password);

            $user->save();

            return success(null, 'Password set successfully.');
        } catch (Exception $e) {
            return $this->error('An error occurred while setting password: ' . $e->getMessage());
        }
    }
}
