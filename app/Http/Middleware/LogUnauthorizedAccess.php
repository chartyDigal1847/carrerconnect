<?php

namespace App\Http\Middleware;

use App\Services\Audit\AccessAttemptLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUnauthorizedAccess
{
    public function __construct(private AccessAttemptLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (in_array($response->getStatusCode(), [401, 403], true)) {
            $body = json_decode($response->getContent(), true);
            $this->logger->logDenied(
                $request,
                $body['error'] ?? 'unauthorized',
                $request->header('X-Role-Attempt'),
                $request->header('X-SSO-Id')
            );
        }

        return $response;
    }
}
