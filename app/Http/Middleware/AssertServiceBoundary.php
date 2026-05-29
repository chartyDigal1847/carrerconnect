<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures this deployment uses an isolated CareerConnect database (SOA boundary).
 */
class AssertServiceBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('careerconnect.boundary.allow_shared_database')) {
            return $next($request);
        }

        $forbidden = config('careerconnect.boundary.forbidden_database_names', []);
        $dbName = strtolower((string) config('careerconnect.database.name'));

        if (in_array($dbName, $forbidden, true)) {
            return response()->json([
                'error' => 'SOA boundary violation',
                'message' => 'CareerConnect must use a dedicated database (e.g. careerconnect), not a shared DEORIS schema.',
            ], 500);
        }

        return $next($request);
    }
}
