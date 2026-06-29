<?php

namespace Tests\Feature\Settings;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MikrotikIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrations_include_mikrotik_provider(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/settings/integrations');

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'mikrotik', 'name' => 'Mikrotik']);
    }

    public function test_mikrotik_test_endpoint_requires_credentials(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/settings/integrations/mikrotik/test');

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
    }
}
