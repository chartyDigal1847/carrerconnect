<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServiceArchitectureTest extends TestCase
{
    public function test_health_endpoint_reports_service_identity(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertJsonStructure(['status', 'service', 'version', 'checks']);
        $response->assertJsonFragment(['service' => config('careerconnect.service_name')]);
    }

    public function test_manifest_describes_soa_boundaries(): void
    {
        $response = $this->getJson('/api/v1/service/manifest');

        $response->assertOk();
        $response->assertJsonPath('architecture.database', 'isolated');
        $response->assertJsonPath('architecture.authentication', 'deoris_portal_sso');
        $response->assertHeader('X-DEORIS-Service', config('careerconnect.service_name'));
    }

    public function test_api_requires_portal_authentication(): void
    {
        $this->getJson('/api/v1/announcements')->assertUnauthorized();
    }
}
