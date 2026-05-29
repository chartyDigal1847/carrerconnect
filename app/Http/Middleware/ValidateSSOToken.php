<?php

namespace App\Http\Middleware;

use App\Contracts\Integration\PortalAuthClient;
use App\Exceptions\Integration\PortalAuthException;
use App\Models\FacultyUser;
use App\Services\Audit\AccessAttemptLogger;
use App\Services\Security\RoleCapabilityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateSSOToken
{
    public function __construct(
        private PortalAuthClient $portal,
        private RoleCapabilityService $roles,
        private AccessAttemptLogger $accessLogger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('faculty')->check()) {
            return $next($request);
        }

        $sessionUserId = $request->session()->get('careerconnect.faculty_id');
        if ($sessionUserId) {
            $user = FacultyUser::find($sessionUserId);
            if ($user && ! $user->isBlocked()) {
                Auth::guard('faculty')->setUser($user);
                $this->accessLogger->logSuccess($request, $user->id, $user->role);

                return $next($request);
            }
        }

        // Dev-mode fallback: accept X-Dev-SSO-Id header in local environment only
        if (app()->environment('local') && $request->header('X-Dev-SSO-Id')) {
            return $this->authenticateDevUser($request, $next, $request->header('X-Dev-SSO-Id'));
        }

        // Dev-mode fallback: accept dev: bearer tokens in local environment only
        if (app()->environment('local')) {
            $token = $request->bearerToken();
            if ($token && str_starts_with($token, 'dev:')) {
                return $this->authenticateDevUser($request, $next, substr($token, 4));
            }
        }

        // Production: require real portal bearer token
        $token = $request->bearerToken();
        if (! $token) {
            $this->accessLogger->logDenied($request, 'missing_token');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->portal->authenticateToken($token);

            // Centralized role validation
            try {
                $this->roles->validateUserAccess($result['user']);
            } catch (\Illuminate\Auth\AuthenticationException $e) {
                $this->accessLogger->logDenied($request, 'access_denied', $result['user']->role, $result['user']->sso_id);

                return response()->json(['error' => $e->getMessage()], 403);
            }

            Auth::guard('faculty')->setUser($result['user']);
            $request->session()->put('careerconnect.faculty_id', $result['user']->id);
            $this->accessLogger->logSuccess($request, $result['user']->id, $result['user']->role);
        } catch (PortalAuthException $e) {
            $this->accessLogger->logDenied($request, $e->getMessage());

            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 401);
        } catch (\Throwable) {
            $this->accessLogger->logDenied($request, 'authentication_failed');

            return response()->json(['error' => 'Authentication failed'], 401);
        }

        return $next($request);
    }

    private function authenticateDevUser(Request $request, Closure $next, string $ssoId): Response
    {
        $user = FacultyUser::where('sso_id', $ssoId)->first();
        if (! $user) {
            $this->accessLogger->logDenied($request, 'dev_user_invalid', null, $ssoId);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $this->roles->validateUserAccess($user);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            $this->accessLogger->logDenied($request, 'access_denied', $user->role, $user->sso_id);

            return response()->json(['error' => $e->getMessage()], 403);
        }

        Auth::guard('faculty')->setUser($user);
        $this->accessLogger->logSuccess($request, $user->id, $user->role);

        return $next($request);
    }
}
