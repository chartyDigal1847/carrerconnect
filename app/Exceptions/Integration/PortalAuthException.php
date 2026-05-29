<?php

namespace App\Exceptions\Integration;

use Exception;

class PortalAuthException extends Exception
{
    public static function invalidToken(): self
    {
        return new self('Invalid or expired portal token', 401);
    }

    public static function studentBlocked(): self
    {
        return new self('Students cannot access this module', 403);
    }

    public static function portalUnavailable(string $reason): self
    {
        return new self('Portal authentication unavailable: '.$reason, 503);
    }
}
