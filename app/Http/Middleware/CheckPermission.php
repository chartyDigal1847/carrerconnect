<?php

namespace App\Http\Middleware;

use App\Services\Security\RoleCapabilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(private RoleCapabilityService $capabilities) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = auth('faculty')->user() ?? auth()->user();

        if (! $user || ! $this->capabilities->can($user, $permission)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        return $next($request);
    }
}
