<?php

namespace App\Services\Audit;

use App\Models\AccessAttempt;
use Illuminate\Http\Request;

class AccessAttemptLogger
{
    public function logDenied(Request $request, string $reason, ?string $role = null, ?string $ssoId = null): void
    {
        try {
            $this->persist([
            'sso_id' => $ssoId,
            'role_attempted' => $role,
            'reason' => $reason,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'was_blocked' => true,
            ]);
        } catch (\Throwable) {
            // Avoid breaking auth flow if audit table unavailable (e.g. in-memory tests)
        }
    }

    public function logSuccess(Request $request, int $userId, string $role): void
    {
        try {
            $this->persist([
            'faculty_user_id' => $userId,
            'role_attempted' => $role,
            'reason' => 'authenticated',
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'was_blocked' => false,
            ]);
        } catch (\Throwable) {
            //
        }
    }

    private function persist(array $data): void
    {
        AccessAttempt::create($data);
    }
}
