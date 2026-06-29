<?php

namespace Tests\Feature\Domain;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_customer(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'operator',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/customers', [
            'name' => 'Rahman Residence',
            'plan' => 'Fiber 100Mbps',
            'status' => 'online',
            'email' => 'rahman@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('billingStatus', 'paid')
            ->assertJsonPath('name', 'Rahman Residence');

        $this->assertDatabaseHas('customers', [
            'name' => 'Rahman Residence',
            'organization_id' => $organization->id,
        ]);
    }

    public function test_customers_are_scoped_to_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $userA = User::factory()->create(['organization_id' => $orgA->id]);

        Customer::query()->create([
            'id' => 'cust-a',
            'organization_id' => $orgA->id,
            'name' => 'Org A Customer',
            'plan' => 'Fiber 100Mbps',
            'status' => 'online',
            'billing_status' => 'paid',
        ]);
        Customer::query()->create([
            'id' => 'cust-b',
            'organization_id' => $orgB->id,
            'name' => 'Org B Customer',
            'plan' => 'Fiber 100Mbps',
            'status' => 'online',
            'billing_status' => 'paid',
        ]);

        $token = $userA->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/v1/customers');

        $response->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', 'cust-a');
    }
}
