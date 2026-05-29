<?php

namespace App\Services\Security;

use App\Models\FacultyUser;

class RoleCapabilityService
{
    /**
     * Check if a role is explicitly blocked from accessing the module.
     */
    public function isBlockedRole(string $role): bool
    {
        return in_array($role, config('careerconnect.roles.blocked', ['student']), true);
    }

    /**
     * Check if a role is allowed to access the module.
     */
    public function isAllowedRole(string $role): bool
    {
        return in_array($role, config('careerconnect.roles.allowed', []), true);
    }

    /**
     * Validate that a user is not blocked and has an allowed role.
     * Throws exception if user should be denied access.
     */
    public function validateUserAccess(FacultyUser $user): void
    {
        if ($user->isBlocked() || $this->isBlockedRole($user->role)) {
            throw new \Illuminate\Auth\AuthenticationException('Students cannot access CareerConnect');
        }

        if (! $this->isAllowedRole($user->role)) {
            throw new \Illuminate\Auth\AuthenticationException('Invalid faculty role');
        }
    }

    /**
     * Check if a user can perform a specific capability.
     */
    public function can(FacultyUser $user, string $capability): bool
    {
        try {
            $this->validateUserAccess($user);
        } catch (\Illuminate\Auth\AuthenticationException) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        $caps = config('careerconnect.roles.capabilities.'.$user->role, []);

        foreach ($caps as $allowed) {
            if ($allowed === $capability) {
                return true;
            }
            if (str_ends_with($allowed, '.*')) {
                $prefix = rtrim($allowed, '.*');
                if (str_starts_with($capability, $prefix)) {
                    return true;
                }
            }
        }

        return $user->hasPermission($capability);
    }
}
