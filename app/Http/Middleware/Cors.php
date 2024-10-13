<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    private const HEADER_ACCESS_CONTROL_MAX_AGE = 86400;

    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', implode(',', $request->allowedHttpVerbs()));
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-Requested-With, Application', 'ip');

        return $response;

        // $response->setHeaders([
        //     'Access-Control-Allow-Origin' => $request->header('Origin'),
        //     'Access-Control-Allow-Methods' => implode(',', $request->allowedHttpVerbs()),
        //     'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, Content-Length, Upload-Key, Upload-Checksum, Upload-Length, Upload-Offset, Tus-Version, Tus-Resumable, Upload-Metadata',
        //     'Access-Control-Expose-Headers' => 'Upload-Key, Upload-Checksum, Upload-Length, Upload-Offset, Upload-Metadata, Tus-Version, Tus-Resumable, Tus-Extension, Location',
        //     'Access-Control-Max-Age' => self::HEADER_ACCESS_CONTROL_MAX_AGE,
        // ]);
    }
}
