<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Mail\ResetPasswordEmail;
use App\Services\SessionTracker;
use App\Traits\HttpResponses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Exception;


class LoginController extends Controller
{
    use HttpResponses;

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

        // Link guest session to user
        $sessionHash = $request->input('session_hash');
        if ($sessionHash) {
            $this->tracker->linkSessionToUser($sessionHash, $user->id);
        }

        $token = $user->createToken($user->name . '-AuthToken-' . now())->plainTextToken;

        return success([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function forgetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $email = $request->email;

            // Find user
            $user = User::where('email', $email)->first();

            // Always return success message for security (don't reveal if email exists)
            if (!$user) {
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
            // Get the authenticated user via Sanctum
            $query = User::query();
            $query->where('id', Auth::id());
            $user = $query->first();

            if (!$user) {
                return $this->error('', 'Invalid token', 401);
            }

            // Link guest session to user
            $sessionHash = $request->input('session_hash');
            if ($sessionHash) {
                $this->tracker->linkSessionToUser($sessionHash, $user->id);
            }

            // Return user info for Node.js chat
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

            // Check if token is expired (60 minutes)
            $tokenCreatedAt = \Carbon\Carbon::parse($resetRecord->created_at);
            if ($tokenCreatedAt->addMinutes(60)->isPast()) {
                DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();
                return $this->error('Password reset token has expired.');
            }

            // Verify token
            if (!Hash::check($request->token, $resetRecord->token)) {
                return $this->error('Invalid password reset token.');
            }

            return success(null, 'Token is valid.');

        } catch (Exception $e) {
            return $this->error('An error occurred while verifying token: ' . $e->getMessage());
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|string|min:8|confirmed', // This checks password_confirmation
            ]);

            // Find reset record
            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$resetRecord) {
                return $this->error('Invalid or expired password reset token.');
            }

            // Check if token is expired (60 minutes)
            $tokenCreatedAt = \Carbon\Carbon::parse($resetRecord->created_at);
            if ($tokenCreatedAt->addMinutes(60)->isPast()) {
                DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();
                return $this->error('Password reset token has expired.');
            }

            // Verify token
            if (!Hash::check($request->token, $resetRecord->token)) {
                return $this->error('Invalid password reset token.');
            }

            // Find user
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return $this->error('User not found.');
            }

            // Update password
            $user->password = Hash::make($request->password);
            $user->save();

            // Delete used token
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return success(null, 'Password has been reset successfully.');

        } catch (Exception $e) {
            return $this->error('An error occurred while resetting password: ' . $e->getMessage());
        }
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('user')->user();

        auth()->user()->tokens()->delete();

        return successMessage('Successfully Logout');
    }
}
