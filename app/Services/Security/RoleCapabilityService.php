<?php

namespace App\Services\Security;

use App\Models\FacultyUser;
use Illuminate\Auth\AuthenticationException;

class RoleCapabilityService
{
    /**
     * Check if a role is explicitly blocked from accessing the module.
     */
    public function isBlockedRole(string $role): bool
    {
        return in_array($role, config('careerconnect.roles.blocked', []), true);
    }

    /**
     * Check if a role is allowed to access the module.
     */
    public function isAllowedRole(string $role): bool
    {
        return in_array($role, config('careerconnect.roles.allowed', []), true);
    }

    /**
     * Validate module access for any allowed role (including students).
     */
    public function validateModuleAccess(FacultyUser $user): void
    {
        if (! $user->is_active) {
            throw new AuthenticationException('Account is inactive');
        }

        if ($this->isBlockedRole($user->role) || ! $this->isAllowedRole($user->role)) {
            throw new AuthenticationException('Role not permitted for CareerConnect');
        }
    }

    /**
     * Validate faculty-only API access (excludes students).
     */
    public function validateFacultyAccess(FacultyUser $user): void
    {
        $this->validateModuleAccess($user);

        if ($user->role === 'student') {
            throw new AuthenticationException('Students cannot access faculty features');
        }
    }

    /**
     * @deprecated Use validateFacultyAccess for faculty routes or validateModuleAccess for SSO.
     */
    public function validateUserAccess(FacultyUser $user): void
    {
        $this->validateFacultyAccess($user);
    }

    /**
     * Check if a user can perform a specific capability.
     */
    public function can(FacultyUser $user, string $capability): bool
    {
        if (! $user->is_active || ! $this->isAllowedRole($user->role)) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($this->roleHasCapability($user->role, $capability)) {
            return true;
        }

        return $user->hasPermission($capability);
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function roleHasCapability(string $role, string $capability): bool
    {
        $caps = config('careerconnect.roles.capabilities.'.$role, []);

        foreach ($caps as $allowed) {
            if ($allowed === $capability) {
                return true;
            }
            if (str_ends_with((string) $allowed, '.*')) {
                $prefix = rtrim((string) $allowed, '.*');
                if (str_starts_with($capability, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
