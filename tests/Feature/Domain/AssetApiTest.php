<?php

namespace Tests\Feature\Domain;

use App\Models\Asset;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_assets(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        Asset::query()->create([
            'id' => 'pop-test-01',
            'organization_id' => $organization->id,
            'kind' => 'pop',
            'name' => 'Test PoP',
            'status' => 'active',
            'location_lat' => 23.81,
            'location_lng' => 90.41,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/assets');

        $response->assertOk()
            ->assertJsonPath('items.0.id', 'pop-test-01');
    }

    public function test_viewer_cannot_create_assets(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'viewer',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/assets', [
            'name' => 'New Pole',
            'kind' => 'pole',
            'status' => 'active',
            'location' => ['lat' => 23.81, 'lng' => 90.41],
        ]);

        $response->assertForbidden();
    }
}
