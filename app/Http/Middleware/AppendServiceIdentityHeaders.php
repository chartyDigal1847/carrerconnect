<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AppendServiceIdentityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-DEORIS-Service', config('careerconnect.service_name'));
        $response->headers->set('X-DEORIS-Service-Version', config('careerconnect.version'));
        $response->headers->set('X-DEORIS-Api-Version', 'v1');

        return $response;
    }
}
