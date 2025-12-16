<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GmailService;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GmailAuthController extends Controller
{
    use HttpResponses;

    /**
     * Get Gmail OAuth authorization URL
     */
    public function getAuthUrl()
    {
        try {
            $gmailService = new GmailService();
            $authUrl = $gmailService->getAuthUrl();

            return $this->success([
                'auth_url' => $authUrl
            ], 'Gmail authorization URL generated');

        } catch (Exception $e) {
            Log::error('Failed to generate Gmail auth URL: ' . $e->getMessage());

            return $this->error(null, 'Failed to generate authorization URL', 500);
        }
    }

    /**
     * Handle OAuth callback and store access token
     */
    public function handleCallback(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        try {
            $gmailService = new GmailService();
            $token = $gmailService->fetchAccessTokenWithAuthCode($request->code);

            if (isset($token['error'])) {
                return $this->error(null, 'Authorization failed: ' . $token['error'], 400);
            }

            // Store the token securely (you may want to encrypt this)
            Cache::put('gmail_access_token', $token, now()->addDays(7));

            // Test the connection
            $gmailService = new GmailService($token);
            $profile = $gmailService->service->users->getProfile('me');

            return $this->success([
                'email_address' => $profile->getEmailAddress(),
                'messages_total' => $profile->getMessagesTotal(),
                'threads_total' => $profile->getThreadsTotal(),
                'token_expires_at' => isset($token['expires_in']) ?
                    now()->addSeconds($token['expires_in'])->toISOString() : null
            ], 'Gmail authorization successful');

        } catch (Exception $e) {
            Log::error('Gmail authorization failed: ' . $e->getMessage());

            return $this->error(null, 'Authorization failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check Gmail connection status
     */
    public function getConnectionStatus()
    {
        try {
            $token = Cache::get('gmail_access_token');

            if (!$token) {
                return $this->success([
                    'connected' => false,
                    'message' => 'No Gmail token found'
                ], 'Gmail not connected');
            }

            $gmailService = new GmailService($token);

            // Test connection by getting profile
            $profile = $gmailService->service->users->getProfile('me');

            return $this->success([
                'connected' => true,
                'email_address' => $profile->getEmailAddress(),
                'messages_total' => $profile->getMessagesTotal(),
                'threads_total' => $profile->getThreadsTotal(),
                'token_expires_at' => isset($token['expires_in']) ?
                    now()->addSeconds($token['expires_in'])->toISOString() : 'Unknown'
            ], 'Gmail connected successfully');

        } catch (Exception $e) {
            Log::error('Gmail connection check failed: ' . $e->getMessage());

            // Clear invalid token
            Cache::forget('gmail_access_token');

            return $this->success([
                'connected' => false,
                'error' => $e->getMessage()
            ], 'Gmail connection failed');
        }
    }

    /**
     * Disconnect Gmail (remove stored tokens)
     */
    public function disconnect()
    {
        try {
            // Revoke the token if possible
            $token = Cache::get('gmail_access_token');
            if ($token && isset($token['access_token'])) {
                $gmailService = new GmailService($token);

                try {
                    $gmailService->client->revokeToken($token['access_token']);
                } catch (Exception $e) {
                    // Continue even if revoke fails
                    Log::warning('Failed to revoke Gmail token: ' . $e->getMessage());
                }
            }

            // Clear stored token
            Cache::forget('gmail_access_token');

            return $this->success(null, 'Gmail disconnected successfully');

        } catch (Exception $e) {
            Log::error('Gmail disconnect failed: ' . $e->getMessage());

            return $this->error(null, 'Failed to disconnect Gmail: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Refresh access token
     */
    public function refreshToken()
    {
        try {
            $token = Cache::get('gmail_access_token');

            if (!$token) {
                return $this->error(null, 'No token to refresh', 400);
            }

            $gmailService = new GmailService($token);

            if ($gmailService->client->isAccessTokenExpired()) {
                if ($gmailService->client->getRefreshToken()) {
                    $newToken = $gmailService->client->fetchAccessTokenWithRefreshToken(
                        $gmailService->client->getRefreshToken()
                    );

                    if (isset($newToken['error'])) {
                        return $this->error(null, 'Token refresh failed: ' . $newToken['error'], 400);
                    }

                    // Store the new token
                    Cache::put('gmail_access_token', $newToken, now()->addDays(7));

                    return $this->success([
                        'token_refreshed' => true,
                        'expires_at' => isset($newToken['expires_in']) ?
                            now()->addSeconds($newToken['expires_in'])->toISOString() : null
                    ], 'Token refreshed successfully');
                } else {
                    return $this->error(null, 'No refresh token available. Re-authorization required.', 400);
                }
            }

            return $this->success([
                'token_refreshed' => false,
                'message' => 'Token is still valid'
            ], 'Token refresh not needed');

        } catch (Exception $e) {
            Log::error('Gmail token refresh failed: ' . $e->getMessage());

            return $this->error(null, 'Token refresh failed: ' . $e->getMessage(), 500);
        }
    }
}
