<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user instanceof \App\Models\Admin && !$user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Your account has been deactivated.',
            ], 403);
        }

        return $next($request);
    }
}
