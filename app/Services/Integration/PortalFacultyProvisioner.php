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

        if ($this->roles->isBlockedRole($role) || ! $this->roles->isAllowedRole($role)) {
            throw new \InvalidArgumentException('role_not_permitted');
        }

        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        if ($email === '') {
            throw new \InvalidArgumentException('Portal claims missing email');
        }

        $attributes = [
            'sso_id' => $ssoId,
            'email' => $email,
            'name' => (string) ($claims['name'] ?? 'Faculty User'),
            'role' => $role,
            'department' => $claims['department'] ?? null,
            'is_active' => true,
        ];

        // Prefer portal sso_id; fall back to email when legacy rows used a different id
        // (e.g. after portal migrate:fresh or manual seeds) to avoid duplicate-email 500s.
        $user = FacultyUser::query()->where('sso_id', $ssoId)->first()
            ?? FacultyUser::withTrashed()->where('email', $email)->first();

        if ($user !== null) {
            if ($user->trashed()) {
                $user->restore();
            }

            $user->fill($attributes);
            $user->save();

            return $user;
        }

        return FacultyUser::create($attributes);
    }

    private function mapPortalRole(string $role): string
    {
        return match ($role) {
            'admin' => 'admin',
            'student' => 'student',
            'instructor', 'faculty' => 'instructor',
            'librarian' => 'librarian',
            'cashier' => 'cashier',
            'admission_officer', 'registrar' => 'admission_officer',
            'career_officer', 'placement_officer', 'career_services', 'hr' => 'career_officer',
            default => $role,
        };
    }
}
