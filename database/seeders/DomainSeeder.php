<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\OrganizationSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DomainSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['name' => 'BCN FiberOps'],
        );

        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password123'),
                'organization_id' => $organization->id,
                'role' => 'admin',
            ]
        );

        app(OrganizationSettingsService::class)->forOrganization($organization->id);

        $assets = [
            ['id' => 'pop-dhaka-01', 'kind' => 'pop', 'name' => 'Dhaka Main PoP', 'status' => 'active', 'location_lat' => 23.8103, 'location_lng' => 90.4125, 'monitor_host' => '192.168.88.1'],
            ['id' => 'onu-cust-001', 'kind' => 'onu', 'name' => 'ONU Rahman Residence', 'status' => 'active', 'location_lat' => 23.7948, 'location_lng' => 90.4088],
            ['id' => 'split-mirpur-01', 'kind' => 'splitter', 'name' => 'Mirpur Splitter 01', 'status' => 'active', 'location_lat' => 23.8065, 'location_lng' => 90.3685],
            ['id' => 'jb-gulshan-01', 'kind' => 'junction_box', 'name' => 'Gulshan Junction Box', 'status' => 'degraded', 'location_lat' => 23.7925, 'location_lng' => 90.4078],
            ['id' => 'pole-banani-01', 'kind' => 'pole', 'name' => 'Banani Pole 01', 'status' => 'active', 'location_lat' => 23.7937, 'location_lng' => 90.4066],
        ];

        foreach ($assets as $asset) {
            Asset::query()->updateOrCreate(
                ['id' => $asset['id']],
                [...$asset, 'organization_id' => $organization->id]
            );
        }

        $customers = [
            [
                'id' => 'cust-001',
                'name' => 'Rahman Residence',
                'plan' => 'Fiber 100Mbps',
                'status' => 'online',
                'billing_status' => 'paid',
                'related_onu_id' => 'onu-cust-001',
                'pppoe_username' => 'samim',
                'email' => 'rahman@example.com',
                'location_lat' => 23.7948,
                'location_lng' => 90.4088,
            ],
            [
                'id' => 'cust-002',
                'name' => 'Karim Enterprise',
                'plan' => 'Fiber 200Mbps',
                'status' => 'unstable',
                'billing_status' => 'overdue',
                'related_onu_id' => null,
                'pppoe_username' => 'tarek',
                'email' => 'karim@example.com',
                'location_lat' => 23.8012,
                'location_lng' => 90.4156,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::query()->updateOrCreate(
                ['id' => $customer['id']],
                [...$customer, 'organization_id' => $organization->id]
            );
        }

        Incident::query()->updateOrCreate(
            ['id' => 'inc-001'],
            [
                'organization_id' => $organization->id,
                'title' => 'Intermittent connectivity in Gulshan',
                'severity' => 'high',
                'status' => 'investigating',
                'related_asset_id' => 'jb-gulshan-01',
                'notes' => 'Multiple customers reporting packet loss.',
            ]
        );

        WorkOrder::query()->updateOrCreate(
            ['id' => 'wo-001'],
            [
                'organization_id' => $organization->id,
                'title' => 'Inspect Gulshan junction box',
                'priority' => 'high',
                'work_type' => 'repair',
                'status' => 'assigned',
                'related_incident_id' => 'inc-001',
                'related_asset_id' => 'jb-gulshan-01',
                'assignee_id' => 'tm-001',
            ]
        );
    }
}
