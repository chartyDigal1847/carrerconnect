<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session cookies for CareerConnect embedded in the DEORIS portal iframe.
 */
class ForceModuleSessionCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'session.domain'      => env('SESSION_DOMAIN', '.deoris.test'),
            'session.secure'      => filter_var(env('SESSION_SECURE_COOKIE', true), FILTER_VALIDATE_BOOL),
            'session.same_site'   => env('SESSION_SAME_SITE', 'none'),
            'session.http_only'   => true,
            'session.partitioned' => filter_var(env('SESSION_PARTITIONED_COOKIE', true), FILTER_VALIDATE_BOOL),
        ]);

        return $next($request);
    }
}
