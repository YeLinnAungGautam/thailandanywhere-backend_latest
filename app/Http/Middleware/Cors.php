<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    // public function handle(Request $request, Closure $next): Response
    // {
    //     $response = $next($request);
    //     $response->headers->set('Access-Control-Allow-Origin', $request->header('Origin'));
    //     $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');
    //     $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, Origin, X-Requested-With, Application', 'ip');

    //     return $response;
    // }

    public function handle($request, Closure $next)
    {
        // Define your allowed origins
        $allowedOrigins = [
            'https://sales-admin.thanywhere.com',
            'http://staging-admin.thanywhere.com',
            'https://staging-admin.thanywhere.com',
            'https://mm.thanywhere.com',
            'https://thanywhere.com',
            'http://localhost:5173',
            'http://localhost:5174'
        ];

        // Get the origin of the incoming request
        $origin = $request->headers->get('Origin');

        // Check if the origin is in the allowed list
        if (in_array($origin, $allowedOrigins)) {
            // Set CORS headers dynamically for allowed origins
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
        }

        return $next($request);
    }
}
