<?php

namespace Tests\Feature\Settings;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class MikrotikCredentialEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mikrotik_password_is_encrypted_at_rest(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'admin',
        ]);

        $this->actingAs($user)->patchJson('/api/v1/settings/integrations/mikrotik', [
            'enabled' => true,
            'host' => '192.168.88.1',
            'port' => 443,
            'username' => 'fiberops',
            'password' => 'secret-router-password',
            'useSsl' => true,
            'apiMode' => 'rest',
        ])->assertOk();

        $settings = app(OrganizationSettingsService::class);
        $raw = $settings->forOrganization($organization->id)->integrations;
        $storedPassword = $raw['credentials']['mikrotik']['password'] ?? '';

        $this->assertNotSame('secret-router-password', $storedPassword);
        $this->assertTrue($raw['credentials']['mikrotik']['passwordEncrypted'] ?? false);

        $credentials = $settings->getMikrotikCredentials($organization->id);
        $this->assertSame('secret-router-password', $credentials['password']);
    }
}
