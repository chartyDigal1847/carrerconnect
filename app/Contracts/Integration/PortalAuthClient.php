<?php

namespace App\Contracts\Integration;

use App\Models\FacultyUser;

/**
 * Authenticates faculty identity via the DEORIS Portal (sole identity provider).
 * No local passwords or shared user tables.
 */
interface PortalAuthClient
{
    /**
     * Validate an SSO bearer token and sync the local faculty profile cache.
     *
     * @return array{user: FacultyUser, claims: array<string, mixed>}
     *
     * @throws \App\Exceptions\Integration\PortalAuthException
     */
    public function authenticateToken(string $token): array;
}
