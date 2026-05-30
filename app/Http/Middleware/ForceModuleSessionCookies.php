<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session cookies for CareerConnect embedded in the DEORIS portal iframe.
 *
 * Reads session cookie settings directly from this module's .env so Docker /
 * config:cache cannot serve stale values from another vhost or build.
 */
class ForceModuleSessionCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        $envPath = base_path('.env');
        $domain = $this->normalizeNullableEnvValue($this->readEnvValue($envPath, 'SESSION_DOMAIN'));
        $sameSite = $this->readEnvValue($envPath, 'SESSION_SAME_SITE') ?: 'none';
        $partitioned = filter_var(
            $this->readEnvValue($envPath, 'SESSION_PARTITIONED_COOKIE') ?? 'false',
            FILTER_VALIDATE_BOOL
        );

        config([
            'session.cookie'      => $this->readEnvValue($envPath, 'SESSION_COOKIE')
                ?: 'careerconnect_session',
            'session.domain'      => $domain ?? config('session.domain'),
            'session.secure'      => filter_var(
                $this->readEnvValue($envPath, 'SESSION_SECURE_COOKIE') ?? 'true',
                FILTER_VALIDATE_BOOL
            ),
            'session.same_site'   => $sameSite,
            'session.http_only'   => true,
            'session.partitioned' => $partitioned,
        ]);

        return $next($request);
    }

    private function readEnvValue(string $envFile, string $key): ?string
    {
        if (! is_readable($envFile)) {
            return null;
        }

        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            if (trim(substr($line, 0, $eq)) !== $key) {
                continue;
            }
            $val = trim(substr($line, $eq + 1));
            if (strlen($val) >= 2 && $val[0] === '"' && $val[-1] === '"') {
                $val = substr($val, 1, -1);
            } elseif (strlen($val) >= 2 && $val[0] === "'" && $val[-1] === "'") {
                $val = substr($val, 1, -1);
            }

            return $val;
        }

        return null;
    }

    private function normalizeNullableEnvValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        return $value;
    }
}
