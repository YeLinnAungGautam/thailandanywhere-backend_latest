<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SessionTracker;
use Symfony\Component\HttpFoundation\Response;

class TrackUserSession
{
    protected $tracker;

    public function __construct(SessionTracker $tracker)
    {
        $this->tracker = $tracker;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Get session hash from header (Vue sends it)
        $sessionHash = $request->header('X-Session-Hash');

        if (!$sessionHash) {
            return $next($request);
        }

        // Validate and update activity
        if ($this->tracker->isValidSession($sessionHash)) {
            $this->tracker->updateActivity($sessionHash);
            $request->attributes->set('tracking_session', $sessionHash);
        }

        return $next($request);
    }
}
