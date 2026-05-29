<?php

namespace App\Services\Integration;

use App\Models\FacultyUser;
use App\Services\Security\RoleCapabilityService;

class PortalFacultyProvisioner
{
    public function __construct(private RoleCapabilityService $roles) {}

    /**
     * Provision (or update) a local faculty user from portal claims.
     * Uses sso_id as the canonical identity key.
     * Email, name, role, and department are synced from portal.
     *
     * @param  array<string, mixed>  $claims
     */
    public function fromPortalClaims(array $claims): FacultyUser
    {
        $ssoId = (string) ($claims['id'] ?? $claims['email'] ?? '');
        if (! $ssoId) {
            throw new \InvalidArgumentException('Portal claims missing id or email');
        }

        $role = $this->mapPortalRole((string) ($claims['role'] ?? ''));

        if ($this->roles->isBlockedRole($role)) {
            throw new \InvalidArgumentException('student_blocked');
        }

        // Use sso_id as the canonical identity key.
        // This ensures that a portal user cannot be duplicated if email changes.
        return FacultyUser::updateOrCreate(
            ['sso_id' => $ssoId],
            [
                'email' => (string) ($claims['email'] ?? ''),
                'name' => (string) ($claims['name'] ?? 'Faculty User'),
                'role' => $role,
                'department' => $claims['department'] ?? null,
                'is_active' => true,
            ]
        );
    }

    private function mapPortalRole(string $role): string
    {
        return match ($role) {
            'admin' => 'admin',
            'instructor', 'faculty' => 'instructor',
            'librarian' => 'librarian',
            'cashier' => 'cashier',
            'admission_officer', 'registrar', 'hr' => 'admission_officer',
            default => $role,
        };
    }
}
