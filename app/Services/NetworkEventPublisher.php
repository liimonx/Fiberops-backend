<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class NetworkEventPublisher
{
    public function publish(int $organizationId, array $message): void
    {
        Redis::publish(
            $this->channelForOrganization($organizationId),
            json_encode($message, JSON_THROW_ON_ERROR)
        );
    }

    public function publishStatusBroadcast(int $organizationId, string $nodeId, string $status): void
    {
        $this->publish($organizationId, [
            'type' => 'status_broadcast',
            'data' => [
                'nodeId' => $nodeId,
                'status' => $status,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    public function publishNodeUpdate(int $organizationId, string $nodeId, array $patch): void
    {
        $this->publish($organizationId, [
            'type' => 'node_update',
            'data' => array_merge(['id' => $nodeId], $patch),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function publishIncidentAlert(int $organizationId, array $data): void
    {
        $this->publish($organizationId, [
            'type' => 'incident_alert',
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function channelForOrganization(int $organizationId): string
    {
        return "org:{$organizationId}:network";
    }
}
