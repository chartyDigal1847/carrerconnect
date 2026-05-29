<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth()->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json(['error' => 'Insufficient role for this action'], 403);
        }

        return $next($request);
    }
}
