<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\OrganizationSetting;
use App\Support\DomainIdGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MikrotikSyncService
{
    public function __construct(
        private readonly OrganizationSettingsService $settingsService,
        private readonly NetworkEventPublisher $publisher,
        private readonly InterfaceStatsService $interfaceStats,
    ) {}

    public function syncAllOrganizations(): void
    {
        OrganizationSetting::query()
            ->cursor()
            ->each(function (OrganizationSetting $settings): void {
                $this->syncOrganization($settings->organization_id);
            });
    }

    public function syncOrganization(int $organizationId): void
    {
        if (config('mikrotik.mock')) {
            return;
        }

        $credentials = $this->settingsService->getMikrotikCredentials($organizationId);

        if ($credentials === null) {
            return;
        }

        try {
            $service = MikrotikService::fromCredentials($credentials);
            $this->syncPppoeSessions($organizationId, $service);
            $this->syncInterfaceStats($organizationId, $service, $credentials);
            $this->syncNetwatchAssets($organizationId, $service);
            $this->settingsService->markMikrotikConnected($organizationId);
        } catch (\Throwable $exception) {
            Log::warning('Mikrotik sync failed', [
                'organizationId' => $organizationId,
                'message' => $exception->getMessage(),
            ]);
            $this->settingsService->markMikrotikError($organizationId);
        }
    }

    private function syncPppoeSessions(int $organizationId, MikrotikService $service): void
    {
        $activeSessions = collect($service->getActivePppoeSessions());

        Customer::query()
            ->forOrganization($organizationId)
            ->whereNotNull('pppoe_username')
            ->where('pppoe_username', '!=', '')
            ->cursor()
            ->each(function (Customer $customer) use ($activeSessions, $organizationId): void {
                $username = strtolower((string) $customer->pppoe_username);
                $isOnline = $activeSessions->contains($username);
                $nextStatus = $isOnline ? 'online' : 'offline';

                if ($customer->status === $nextStatus) {
                    return;
                }

                $customer->update(['status' => $nextStatus]);
                $this->publisher->publishStatusBroadcast(
                    $organizationId,
                    $customer->id,
                    $this->mapCustomerStatusToNetworkStatus($nextStatus)
                );
            });
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function syncInterfaceStats(
        int $organizationId,
        MikrotikService $service,
        array $credentials
    ): void {
        $interfaceName = (string) ($credentials['monitoredInterface'] ?? '');

        if ($interfaceName === '') {
            return;
        }

        $pollKey = "org:{$organizationId}:mikrotik:interface:last_poll";
        $pollInterval = config('mikrotik.interface_poll_interval_seconds', 60);
        $lastPoll = Redis::get($pollKey);

        if ($lastPoll !== null && (time() - (int) $lastPoll) < $pollInterval) {
            return;
        }

        Redis::setex($pollKey, $pollInterval * 2, (string) time());

        $stats = $service->getInterfaceStats($interfaceName);

        if ($stats === null) {
            return;
        }

        $mbps = $this->interfaceStats->recordSample($organizationId, $stats);
        $utilization = min(100, (int) round(($mbps / 1000) * 100));

        Asset::query()
            ->forOrganization($organizationId)
            ->where('kind', 'pop')
            ->orderBy('name')
            ->limit(1)
            ->get()
            ->each(function (Asset $asset) use ($organizationId, $utilization): void {
                $this->publisher->publishNodeUpdate($organizationId, $asset->id, [
                    'utilization' => $utilization,
                ]);
            });
    }

    private function syncNetwatchAssets(int $organizationId, MikrotikService $service): void
    {
        $entries = collect($service->getNetwatchEntries())
            ->keyBy(fn (array $entry) => strtolower($entry['host']));

        Asset::query()
            ->forOrganization($organizationId)
            ->whereNotNull('monitor_host')
            ->where('monitor_host', '!=', '')
            ->cursor()
            ->each(function (Asset $asset) use ($entries, $organizationId): void {
                $host = strtolower((string) $asset->monitor_host);
                $entry = $entries->get($host);
                $nextStatus = $this->mapNetwatchToAssetStatus($entry['status'] ?? 'unknown');

                if ($asset->status === $nextStatus) {
                    return;
                }

                $previousStatus = $asset->status;
                $asset->update(['status' => $nextStatus]);
                $this->publisher->publishStatusBroadcast(
                    $organizationId,
                    $asset->id,
                    $this->mapAssetStatusToNetworkStatus($nextStatus)
                );

                if ($nextStatus === 'down' && $previousStatus !== 'down') {
                    $hasOpenIncident = Incident::query()
                        ->forOrganization($organizationId)
                        ->where('related_asset_id', $asset->id)
                        ->where('status', '!=', 'resolved')
                        ->where('title', 'like', 'Monitor host unreachable:%')
                        ->exists();

                    if ($hasOpenIncident) {
                        return;
                    }

                    $incident = Incident::query()->create([
                        'id' => DomainIdGenerator::nextIncidentId($organizationId),
                        'organization_id' => $organizationId,
                        'title' => "Monitor host unreachable: {$asset->name}",
                        'severity' => 'high',
                        'status' => 'new',
                        'related_asset_id' => $asset->id,
                        'notes' => "Netwatch reported {$asset->monitor_host} as down.",
                    ]);

                    $this->publisher->publishIncidentAlert($organizationId, [
                        'incidentId' => $incident->id,
                        'title' => $incident->title,
                        'severity' => $incident->severity,
                        'relatedAssetId' => $asset->id,
                    ]);
                }
            });
    }

    private function mapCustomerStatusToNetworkStatus(string $status): string
    {
        return match ($status) {
            'online' => 'active',
            'unstable' => 'warning',
            default => 'error',
        };
    }

    private function mapAssetStatusToNetworkStatus(string $status): string
    {
        return match ($status) {
            'active' => 'active',
            'degraded' => 'warning',
            'maintenance' => 'inactive',
            default => 'error',
        };
    }

    private function mapNetwatchToAssetStatus(string $status): string
    {
        return match ($status) {
            'up' => 'active',
            'down' => 'down',
            default => 'degraded',
        };
    }
}
