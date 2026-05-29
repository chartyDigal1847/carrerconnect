<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Integration\PortalAuthClient;
use App\Exceptions\Integration\PortalAuthException;
use App\Services\Integration\PortalFacultyProvisioner;
use App\Services\Security\RoleCapabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SsoController
{
    public function __construct(
        private PortalAuthClient $portal,
        private PortalFacultyProvisioner $provisioner,
        private RoleCapabilityService $roles,
    ) {}

    /**
     * Create a module session after portal SSO.
     *
     * Accepts either:
     * - { token } — server exchanges with portal (production / CLI)
     * - { user } + X-CareerConnect-Sso-Key — browser already exchanged with portal (local HTTPS)
     */
    public function exchange(Request $request): JsonResponse
    {
        $embedded = (bool) $request->boolean('embedded');

        if ($request->has('user') && is_array($request->input('user'))) {
            return $this->exchangeFromPortalUser($request, $request->input('user'), $embedded);
        }

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'embedded' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $this->portal->authenticateToken($validated['token']);

            return $this->startSession($request, $result['user'], (bool) ($validated['embedded'] ?? false));
        } catch (PortalAuthException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $e->getCode() >= 400 ? $e->getCode() : 401);
        }
    }

    /**
     * @param  array<string, mixed>  $portalUser
     */
    private function exchangeFromPortalUser(Request $request, array $portalUser, bool $embedded): JsonResponse
    {
        if (! $this->validClientHandshake($request)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_handshake',
                'message' => 'Invalid SSO handshake',
            ], 403);
        }

        if (empty($portalUser['id']) || empty($portalUser['email'])) {
            return response()->json([
                'success' => false,
                'error' => 'missing_user',
                'message' => 'Portal user identity incomplete',
            ], 422);
        }

        try {
            $user = $this->provisioner->fromPortalClaims($portalUser);
        } catch (\InvalidArgumentException) {
            return response()->json([
                'success' => false,
                'error' => 'access_denied',
                'message' => 'Role not permitted for CareerConnect',
            ], 403);
        }

        return $this->startSession($request, $user, $embedded);
    }

    private function validClientHandshake(Request $request): bool
    {
        $expected = (string) config('careerconnect.service_key', '');
        $provided = (string) $request->header('X-CareerConnect-Sso-Key', '');

        return $expected !== '' && hash_equals($expected, $provided);
    }

    private function startSession(Request $request, $user, bool $embedded): JsonResponse
    {
        try {
            $this->roles->validateModuleAccess($user);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'access_denied',
                'message' => $e->getMessage(),
            ], 403);
        }

        $request->session()->regenerate();
        Auth::guard('faculty')->setUser($user);
        $request->session()->put('careerconnect.faculty_id', $user->id);
        $request->session()->put([
            'sso_id' => (string) $user->sso_id,
            'sso_name' => (string) $user->name,
            'sso_email' => strtolower((string) $user->email),
            'sso_role' => (string) $user->role,
            'sso_authenticated_at' => now()->timestamp,
        ]);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->sso_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'embedded' => $embedded,
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'revoked' => false]);
    }
}
