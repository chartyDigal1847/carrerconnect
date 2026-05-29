<?php

namespace App\Http\Middleware;

use App\Services\Audit\AccessAttemptLogger;
use App\Services\Security\RoleCapabilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockStudents
{
    public function __construct(
        private RoleCapabilityService $roles,
        private AccessAttemptLogger $accessLogger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $this->roles->validateUserAccess($user);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            $this->accessLogger->logDenied($request, 'access_denied', $user->role, $user->sso_id);

            return response()->json(['error' => $e->getMessage()], 403);
        }

        return $next($request);
    }
}
