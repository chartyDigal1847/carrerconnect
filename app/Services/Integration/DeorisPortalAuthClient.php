<?php

namespace App\Services\Integration;

use App\Contracts\Integration\PortalAuthClient;
use App\Exceptions\Integration\PortalAuthException;
use App\Models\FacultyUser;
use Illuminate\Support\Facades\Http;

class DeorisPortalAuthClient implements PortalAuthClient
{
    public function __construct(private PortalFacultyProvisioner $provisioner) {}

    public function authenticateToken(string $token): array
    {
        if (app()->environment('local') && str_starts_with($token, 'dev:')) {
            $user = FacultyUser::where('sso_id', substr($token, 4))->firstOrFail();

            return ['user' => $user, 'claims' => ['id' => $user->sso_id, 'role' => $user->role]];
        }

        $baseUrl = rtrim(config('careerconnect.integrations.portal.base_url'), '/');
        $path = config('careerconnect.integrations.portal.exchange_path');
        $timeout = config('careerconnect.integrations.portal.timeout', 8);

        try {
            $response = $this->portalHttp($timeout)
                ->withHeaders(['Authorization' => 'Bearer '.$token])
                ->post($baseUrl.$path, ['token' => $token]);
        } catch (\Throwable $e) {
            throw PortalAuthException::portalUnavailable($e->getMessage());
        }

        if (! $response->successful()) {
            throw PortalAuthException::invalidToken();
        }

        $body = $response->json();
        if (($body['success'] ?? false) !== true) {
            throw PortalAuthException::invalidToken();
        }

        $claims = $body['user'] ?? [];

        try {
            $user = $this->provisioner->fromPortalClaims($claims);
        } catch (\InvalidArgumentException) {
            throw PortalAuthException::studentBlocked();
        }

        return ['user' => $user, 'claims' => $claims];
    }

    private function portalHttp(int $timeout): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::timeout($timeout)->acceptJson();

        if (! config('careerconnect.integrations.portal.verify_ssl', true)) {
            $client = $client->withOptions(['verify' => false]);
        }

        return $client;
    }
}
