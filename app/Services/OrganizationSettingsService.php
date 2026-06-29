<?php

namespace App\Services;

use App\Models\OrganizationSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class OrganizationSettingsService
{
    public function forOrganization(int $organizationId): OrganizationSetting
    {
        return OrganizationSetting::query()->firstOrCreate(
            ['organization_id' => $organizationId],
            [
                'organization' => [
                    'organizationName' => 'BCN FiberOps',
                    'supportEmail' => 'support@bcn-fiberops.com',
                ],
                'integrations' => $this->defaultIntegrations(),
                'billing' => $this->defaultBilling(),
                'team' => $this->defaultTeam(),
            ]
        );
    }

    public function getOrganization(int $organizationId): array
    {
        return $this->forOrganization($organizationId)->organization ?? [];
    }

    public function updateOrganization(int $organizationId, array $data): array
    {
        $settings = $this->forOrganization($organizationId);
        $settings->update(['organization' => $data]);

        return $settings->organization ?? [];
    }

    public function getIntegrations(int $organizationId): array
    {
        $settings = $this->forOrganization($organizationId);
        $state = $this->mergeMissingIntegrations($settings->integrations ?? $this->defaultIntegrations());
        if ($state !== ($settings->integrations ?? null)) {
            $settings->update(['integrations' => $state]);
        }

        return $this->toPublicIntegrations($state);
    }

    public function updateIntegration(int $organizationId, string $providerId, array $data): array
    {
        $settings = $this->forOrganization($organizationId);
        $state = $settings->integrations ?? $this->defaultIntegrations();
        $integrations = collect($state['integrations'] ?? []);
        $credentials = $state['credentials'] ?? [];

        $integrations = $integrations->map(function (array $integration) use ($providerId, $data, &$credentials) {
            if ($integration['id'] !== $providerId) {
                return $integration;
            }

            if (array_key_exists('enabled', $data)) {
                $integration['enabled'] = (bool) $data['enabled'];
            }

            $cred = $credentials[$providerId] ?? [];
            if (! empty($data['apiKey'])) {
                $cred['apiKey'] = $data['apiKey'];
            }
            if (! empty($data['webhookUrl'])) {
                $cred['webhookUrl'] = $data['webhookUrl'];
            }
            if (! empty($data['routingKey'])) {
                $cred['routingKey'] = $data['routingKey'];
            }
            if ($providerId === 'mikrotik') {
                foreach (['host', 'username', 'monitoredInterface', 'apiMode'] as $field) {
                    if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                        $cred[$field] = $data[$field];
                    }
                }
                if (! empty($data['password'])) {
                    $cred['password'] = Crypt::encryptString($data['password']);
                    $cred['passwordEncrypted'] = true;
                }
                if (array_key_exists('port', $data) && $data['port'] !== null && $data['port'] !== '') {
                    $cred['port'] = (int) $data['port'];
                }
                if (array_key_exists('useSsl', $data)) {
                    $cred['useSsl'] = (bool) $data['useSsl'];
                }
                if (array_key_exists('verifySsl', $data)) {
                    $cred['verifySsl'] = (bool) $data['verifySsl'];
                }
            }
            $credentials[$providerId] = $cred;

            return $integration;
        });

        $state['integrations'] = $integrations->values()->all();
        $state['credentials'] = $credentials;
        $settings->update(['integrations' => $state]);

        return $this->toPublicIntegrations($state);
    }

    public function updateWebhook(int $organizationId, array $data): array
    {
        $settings = $this->forOrganization($organizationId);
        $state = $settings->integrations ?? $this->defaultIntegrations();
        $webhook = $state['outboundWebhook'] ?? [];

        if (array_key_exists('enabled', $data)) {
            $webhook['enabled'] = (bool) $data['enabled'];
        }
        if (array_key_exists('url', $data)) {
            $webhook['url'] = $data['url'];
        }
        if (! empty($data['secret'])) {
            $webhook['secret'] = $data['secret'];
        }
        if (array_key_exists('events', $data)) {
            $webhook['events'] = $data['events'];
        }

        $state['outboundWebhook'] = $webhook;
        $settings->update(['integrations' => $state]);

        return $this->toPublicIntegrations($state);
    }

    public function getBillingPayload(int $organizationId): array
    {
        $billing = $this->forOrganization($organizationId)->billing ?? $this->defaultBilling();

        return [
            'settings' => $billing['settings'] ?? [],
            'stripe' => $billing['stripe'] ?? ['status' => 'disconnected', 'enabled' => false],
        ];
    }

    public function updateBilling(int $organizationId, array $data): array
    {
        $settings = $this->forOrganization($organizationId);
        $billing = $settings->billing ?? $this->defaultBilling();
        $billing['settings'] = array_merge($billing['settings'] ?? [], $data);
        $settings->update(['billing' => $billing]);

        return $this->getBillingPayload($organizationId);
    }

    public function syncBilling(int $organizationId): array
    {
        $settings = $this->forOrganization($organizationId);
        $billing = $settings->billing ?? $this->defaultBilling();
        $billing['settings']['lastSyncedAt'] = now()->toIso8601String();
        $settings->update(['billing' => $billing]);

        return $this->getBillingPayload($organizationId);
    }

    public function getTeam(int $organizationId): array
    {
        return $this->forOrganization($organizationId)->team ?? $this->defaultTeam();
    }

    public function inviteTeamMember(int $organizationId, array $data): array
    {
        $settings = $this->forOrganization($organizationId);
        $team = $settings->team ?? $this->defaultTeam();
        $invites = $team['invites'] ?? [];
        $invites[] = [
            'id' => 'inv-'.str_pad((string) (count($invites) + 1), 3, '0', STR_PAD_LEFT),
            'email' => $data['email'],
            'role' => $data['role'],
            'invitedAt' => now()->toIso8601String(),
        ];
        $team['invites'] = $invites;
        $settings->update(['team' => $team]);

        return $team;
    }

    public function updateTeamMember(int $organizationId, string $memberId, array $data): array
    {
        $settings = $this->forOrganization($organizationId);
        $team = $settings->team ?? $this->defaultTeam();
        $team['members'] = collect($team['members'] ?? [])->map(function (array $member) use ($memberId, $data) {
            if ($member['id'] !== $memberId) {
                return $member;
            }
            $member['role'] = $data['role'];

            return $member;
        })->values()->all();
        $settings->update(['team' => $team]);

        return $team;
    }

    public function revokeInvite(int $organizationId, string $inviteId): array
    {
        $settings = $this->forOrganization($organizationId);
        $team = $settings->team ?? $this->defaultTeam();
        $team['invites'] = collect($team['invites'] ?? [])
            ->reject(fn (array $invite) => $invite['id'] === $inviteId)
            ->values()
            ->all();
        $settings->update(['team' => $team]);

        return $team;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMikrotikCredentials(int $organizationId): ?array
    {
        $state = $this->forOrganization($organizationId)->integrations ?? $this->defaultIntegrations();
        $integration = collect($state['integrations'] ?? [])
            ->firstWhere('id', 'mikrotik');

        if (! is_array($integration) || ! ($integration['enabled'] ?? false)) {
            return null;
        }

        $credentials = $state['credentials']['mikrotik'] ?? [];
        $host = (string) ($credentials['host'] ?? '');
        $username = (string) ($credentials['username'] ?? '');
        $password = $this->resolveMikrotikPassword($credentials);

        if ($host === '' || $username === '' || $password === '') {
            return null;
        }

        return [
            'host' => $host,
            'port' => (int) ($credentials['port'] ?? (($credentials['useSsl'] ?? true) ? 443 : 8728)),
            'username' => $username,
            'password' => $password,
            'useSsl' => (bool) ($credentials['useSsl'] ?? true),
            'verifySsl' => (bool) ($credentials['verifySsl'] ?? true),
            'apiMode' => (string) ($credentials['apiMode'] ?? 'rest'),
            'monitoredInterface' => (string) ($credentials['monitoredInterface'] ?? ''),
        ];
    }

    public function markMikrotikConnected(int $organizationId): void
    {
        $this->setMikrotikStatus($organizationId, 'connected');
    }

    public function markMikrotikError(int $organizationId): void
    {
        $this->setMikrotikStatus($organizationId, 'error');
    }

    public function testMikrotikConnection(int $organizationId, ?array $override = null): array
    {
        $credentials = $override ?? $this->getMikrotikCredentials($organizationId);

        if ($credentials === null) {
            return [
                'ok' => false,
                'message' => 'Mikrotik credentials are incomplete.',
            ];
        }

        $result = MikrotikService::fromCredentials($credentials)->testConnection();

        if ($result['ok']) {
            $this->markMikrotikConnected($organizationId);
        } else {
            $this->markMikrotikError($organizationId);
        }

        return $result;
    }

    private function setMikrotikStatus(int $organizationId, string $status): void
    {
        $settings = $this->forOrganization($organizationId);
        $state = $settings->integrations ?? $this->defaultIntegrations();
        $state['integrations'] = collect($state['integrations'] ?? [])
            ->map(function (array $integration) use ($status) {
                if (($integration['id'] ?? null) !== 'mikrotik') {
                    return $integration;
                }

                $integration['status'] = $status;

                return $integration;
            })
            ->values()
            ->all();

        $settings->update(['integrations' => $state]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultIntegrations(): array
    {
        return [
            'integrations' => [
                ['id' => 'mapbox', 'name' => 'Mapbox', 'description' => 'GIS map tiles and geocoding', 'status' => 'disconnected', 'enabled' => false],
                ['id' => 'slack', 'name' => 'Slack', 'description' => 'Incident notifications', 'status' => 'disconnected', 'enabled' => false],
                ['id' => 'pagerduty', 'name' => 'PagerDuty', 'description' => 'On-call escalation', 'status' => 'disconnected', 'enabled' => false],
                ['id' => 'stripe', 'name' => 'Stripe', 'description' => 'Billing sync', 'status' => 'disconnected', 'enabled' => false],
                ['id' => 'mikrotik', 'name' => 'Mikrotik', 'description' => 'RouterOS PPPoE, interface, and netwatch monitoring', 'status' => 'disconnected', 'enabled' => false],
            ],
            'credentials' => [],
            'outboundWebhook' => [
                'enabled' => false,
                'url' => '',
                'events' => ['incident.created', 'incident.resolved'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultBilling(): array
    {
        return [
            'settings' => [
                'legalName' => 'BCN FiberOps Ltd.',
                'billingEmail' => 'billing@bcn-fiberops.com',
                'currency' => 'USD',
                'taxId' => '',
                'invoiceDelivery' => 'email',
                'lastSyncedAt' => null,
            ],
            'stripe' => [
                'status' => 'disconnected',
                'enabled' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultTeam(): array
    {
        return [
            'members' => [
                [
                    'id' => 'tm-001',
                    'name' => 'Jordan Lee',
                    'email' => 'jordan@bcn-fiberops.com',
                    'role' => 'admin',
                    'lastActiveAt' => now()->subHours(2)->toIso8601String(),
                ],
            ],
            'invites' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function mergeMissingIntegrations(array $state): array
    {
        $defaults = $this->defaultIntegrations();
        $existingIds = collect($state['integrations'] ?? [])->pluck('id')->all();
        $missing = collect($defaults['integrations'] ?? [])
            ->reject(fn (array $integration) => in_array($integration['id'], $existingIds, true))
            ->values()
            ->all();

        if ($missing === []) {
            return $state;
        }

        $state['integrations'] = array_merge($state['integrations'] ?? [], $missing);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function toPublicIntegrations(array $state): array
    {
        $credentials = $state['credentials'] ?? [];
        $integrations = collect($state['integrations'] ?? [])->map(function (array $integration) use ($credentials) {
            $cred = $credentials[$integration['id']] ?? [];
            $public = [...$integration];

            if (($integration['id'] ?? null) === 'mikrotik') {
                if (! empty($cred['host'])) {
                    $public['host'] = $cred['host'];
                }
                if (! empty($cred['port'])) {
                    $public['port'] = (int) $cred['port'];
                }
                if (! empty($cred['username'])) {
                    $public['username'] = $cred['username'];
                }
                if (! empty($cred['password'])) {
                    $public['passwordMasked'] = '••••••••'.substr($cred['password'], -4);
                }
                if (array_key_exists('useSsl', $cred)) {
                    $public['useSsl'] = (bool) $cred['useSsl'];
                }
                if (! empty($cred['monitoredInterface'])) {
                    $public['monitoredInterface'] = $cred['monitoredInterface'];
                }
                if (! empty($cred['apiMode'])) {
                    $public['apiMode'] = $cred['apiMode'];
                }
            } else {
                $masked = null;
                if (! empty($cred['apiKey'])) {
                    $masked = '••••••••'.substr($cred['apiKey'], -4);
                } elseif (! empty($cred['webhookUrl'])) {
                    $masked = $cred['webhookUrl'];
                } elseif (! empty($cred['routingKey'])) {
                    $masked = '••••••••'.substr($cred['routingKey'], -4);
                }

                if ($masked !== null) {
                    $public['apiKeyMasked'] = $masked;
                }
            }

            return array_filter($public, fn ($value) => $value !== null);
        })->values()->all();

        $webhook = $state['outboundWebhook'] ?? [];
        $publicWebhook = [
            'enabled' => $webhook['enabled'] ?? false,
            'url' => $webhook['url'] ?? '',
            'events' => $webhook['events'] ?? [],
        ];
        if (! empty($webhook['secret'])) {
            $publicWebhook['secretMasked'] = '••••••••'.substr($webhook['secret'], -4);
        }

        return [
            'integrations' => $integrations,
            'outboundWebhook' => $publicWebhook,
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function resolveMikrotikPassword(array $credentials): string
    {
        $stored = (string) ($credentials['password'] ?? '');

        if ($stored === '') {
            return '';
        }

        if ($credentials['passwordEncrypted'] ?? false) {
            try {
                return Crypt::decryptString($stored);
            } catch (DecryptException) {
                return '';
            }
        }

        return $stored;
    }
}
