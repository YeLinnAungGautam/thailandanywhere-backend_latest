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
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Access-Control-Allow-Origin', $request->header('Origin'));
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');
        $response->headers->set('Access-Control-Allow-Credentials', true);
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, Application', 'ip');

        return $response;
    }

    // public function handle($request, Closure $next)
    // {
    //     $allowedOrigins = [
    //         'https://sales-admin.thanywhere.com',
    //         'https://blog.thanywhere.com',
    //         // Add other allowed domains here
    //     ];

    //     $origin = $request->header('Origin');

    //     if (in_array($origin, $allowedOrigins)) {
    //         $headers = [
    //             'Access-Control-Allow-Origin' => $origin,
    //             'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
    //             'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin, Authorization',
    //         ];

    //         $response = $next($request);
    //         foreach ($headers as $key => $value) {
    //             $response->header($key, $value);
    //         }

    //         return $response;
    //     }

    //     return $next($request);
    // }
}
