<?php

namespace App\Services;

use App\Models\UserSession;
use App\Models\FunnelEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class SessionTracker
{
    /**
     * Create new session
     */
    public function createNewSession(Request $request): string
    {
        $sessionHash = $this->generateSessionHash($request);
        $fingerprint = $this->generateFingerprint($request);
        $deviceType = $this->detectDeviceType($request);
        $isBot = $this->isBot($request);

        UserSession::create([
            'session_hash' => $sessionHash,
            'user_id' => auth()->id(),
            'fingerprint' => $fingerprint,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $deviceType,
            'first_visit_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addHours(24),
            'is_active' => true,
            'is_bot' => $isBot,
        ]);

        Cache::put("session:{$sessionHash}", true, now()->addHours(24));

        return $sessionHash;
    }

    /**
     * Validate session
     */
    public function isValidSession(string $sessionHash): bool
    {
        if (Cache::has("session:{$sessionHash}")) {
            return true;
        }

        $exists = UserSession::where('session_hash', $sessionHash)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->exists();

        if ($exists) {
            Cache::put("session:{$sessionHash}", true, now()->addHours(24));
            return true;
        }

        return false;
    }

    /**
     * Update activity
     */
    public function updateActivity(string $sessionHash): void
    {
        $cacheKey = "activity:{$sessionHash}";

        if (!Cache::has($cacheKey)) {
            UserSession::where('session_hash', $sessionHash)
                ->update([
                    'last_activity_at' => now(),
                    'expires_at' => now()->addHours(24),
                ]);

            Cache::put($cacheKey, true, now()->addMinutes(5));
        }
    }

    /**
     * Link session to user
     */
    public function linkSessionToUser(string $sessionHash, int $userId): void
    {
        UserSession::where('session_hash', $sessionHash)
            ->whereNull('user_id')
            ->update(['user_id' => $userId]);

        Cache::forget("session:{$sessionHash}");
    }

    /**
     * ✅ Track event (with duplicate check)
     */
    public function trackEvent(
        string $sessionHash,
        string $eventType,
        ?string $productType = null,
        ?int $productId = null,
        array $metadata = []
    ): void {
        $session = UserSession::where('session_hash', $sessionHash)->first();

        if (!$session) {
            return;
        }

        // ✅ Check for duplicate events (prevent multiple visit_site, view_detail)
        if (in_array($eventType, ['visit_site', 'view_detail'])) {
            $exists = $this->eventExists(
                $session->id,
                $eventType,
                $productType,
                $productId
            );

            if ($exists) {
                // Event already tracked, skip
                return;
            }
        }

        // Create new event
        FunnelEvent::create([
            'session_id' => $session->id,
            'product_type' => $productType,
            'product_id' => $productId,
            'event_type' => $eventType,
            'event_value' => $metadata['value'] ?? null,
            'quantity' => $metadata['quantity'] ?? 1,
            'metadata' => !empty($metadata) ? $metadata : null,
        ]);
    }

    /**
     * ✅ Check if event already exists
     */
    protected function eventExists(
        int $sessionId,
        string $eventType,
        ?string $productType = null,
        ?int $productId = null
    ): bool {
        // Cache key
        $cacheKey = "event:{$sessionId}:{$eventType}";

        if ($productType && $productId) {
            $cacheKey .= ":{$productType}:{$productId}";
        }

        // Check cache first
        if (Cache::has($cacheKey)) {
            return true;
        }

        // Check database
        $query = FunnelEvent::where('session_id', $sessionId)
            ->where('event_type', $eventType);

        if ($eventType === 'visit_site') {
            $exists = $query->exists();
        } elseif ($eventType === 'view_detail' && $productType && $productId) {
            $exists = $query->where('product_type', $productType)
                           ->where('product_id', $productId)
                           ->exists();
        } else {
            return false;
        }

        // Cache for 24 hours if exists
        if ($exists) {
            Cache::put($cacheKey, true, now()->addHours(24));
        }

        return $exists;
    }

    /**
     * Generate session hash
     */
    protected function generateSessionHash(Request $request): string
    {
        $data = implode('|', [
            $request->ip(),
            $request->userAgent(),
            now()->timestamp,
            Str::random(16),
        ]);

        return hash('sha256', $data);
    }

    /**
     * Generate fingerprint
     */
    protected function generateFingerprint(Request $request): string
    {
        $data = implode('|', [
            $request->ip(),
            $request->userAgent(),
            $request->header('Accept-Language', ''),
            $request->header('Accept-Encoding', ''),
        ]);

        return hash('md5', $data);
    }

    /**
     * Detect device type
     */
    protected function detectDeviceType(Request $request): string
    {
        $userAgent = strtolower($request->userAgent() ?? '');

        if (preg_match('/mobile|android|iphone|ipod|phone/i', $userAgent)) {
            return 'mobile';
        }

        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Check if bot
     */
    protected function isBot(Request $request): bool
    {
        $userAgent = strtolower($request->userAgent() ?? '');

        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp',
            'googlebot', 'bingbot', 'yandex', 'baidu'
        ];

        foreach ($botPatterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
