<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsForOrganization;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OrganizationSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    use RespondsForOrganization;

    public function __construct(private readonly OrganizationSettingsService $settings) {}

    public function getOrganization(): JsonResponse
    {
        return response()->json($this->settings->getOrganization($this->organizationId()));
    }

    public function updateOrganization(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $validated = $request->validate([
            'organizationName' => ['required', 'string', 'min:2'],
            'supportEmail' => ['required', 'email'],
        ]);

        return response()->json(
            $this->settings->updateOrganization($this->organizationId(), $validated)
        );
    }

    public function getIntegrations(): JsonResponse
    {
        return response()->json($this->settings->getIntegrations($this->organizationId()));
    }

    public function updateIntegration(Request $request, string $providerId): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $validIds = ['mapbox', 'slack', 'pagerduty', 'stripe', 'mikrotik'];
        if (! in_array($providerId, $validIds, true)) {
            return $this->notFound('Integration not found');
        }

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'apiKey' => ['nullable', 'string'],
            'webhookUrl' => ['nullable', 'url'],
            'routingKey' => ['nullable', 'string'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'useSsl' => ['sometimes', 'boolean'],
            'verifySsl' => ['sometimes', 'boolean'],
            'apiMode' => ['nullable', Rule::in(['rest', 'classic'])],
            'monitoredInterface' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(
            $this->findIntegration($this->settings->updateIntegration($this->organizationId(), $providerId, $validated), $providerId)
        );
    }

    public function updateWebhook(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'url' => ['sometimes', 'url'],
            'secret' => ['nullable', 'string'],
            'events' => ['sometimes', 'array'],
            'events.*' => [Rule::in(['incident.created', 'incident.resolved', 'outage.detected', 'work_order.updated'])],
        ]);

        return response()->json(
            $this->settings->updateWebhook($this->organizationId(), $validated)['outboundWebhook']
        );
    }

    public function testMikrotikConnection(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $validated = $request->validate([
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'useSsl' => ['sometimes', 'boolean'],
            'verifySsl' => ['sometimes', 'boolean'],
            'apiMode' => ['nullable', Rule::in(['rest', 'classic'])],
        ]);

        $override = null;
        if (! empty($validated['host']) && ! empty($validated['username']) && ! empty($validated['password'])) {
            $override = [
                'host' => $validated['host'],
                'port' => (int) ($validated['port'] ?? (($validated['useSsl'] ?? true) ? 443 : 8728)),
                'username' => $validated['username'],
                'password' => $validated['password'],
                'useSsl' => (bool) ($validated['useSsl'] ?? true),
                'verifySsl' => (bool) ($validated['verifySsl'] ?? true),
                'apiMode' => (string) ($validated['apiMode'] ?? 'rest'),
                'monitoredInterface' => '',
            ];
        }

        $result = $this->settings->testMikrotikConnection($this->organizationId(), $override);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function getBilling(): JsonResponse
    {
        return response()->json($this->settings->getBillingPayload($this->organizationId()));
    }

    public function updateBilling(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $validated = $request->validate([
            'legalName' => ['required', 'string'],
            'billingEmail' => ['required', 'email'],
            'currency' => ['required', Rule::in(['USD', 'EUR', 'GBP', 'CAD'])],
            'taxId' => ['nullable', 'string'],
            'invoiceDelivery' => ['required', Rule::in(['email', 'portal'])],
        ]);

        return response()->json(
            $this->settings->updateBilling($this->organizationId(), $validated)
        );
    }

    public function syncBilling(): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        return response()->json($this->settings->syncBilling($this->organizationId()));
    }

    public function getTeam(): JsonResponse
    {
        return response()->json($this->settings->getTeam($this->organizationId()));
    }

    public function inviteTeamMember(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        /** @var User $user */
        $user = auth()->user();
        if (! $user->isAdmin()) {
            return $this->forbidden('Only admins can invite team members.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(['admin', 'operator', 'viewer'])],
        ]);

        return response()->json(
            $this->settings->inviteTeamMember($this->organizationId(), $validated),
            201
        );
    }

    public function updateTeamMember(Request $request, string $memberId): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        /** @var User $user */
        $user = auth()->user();
        if (! $user->isAdmin()) {
            return $this->forbidden('Only admins can update team roles.');
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'operator', 'viewer'])],
        ]);

        return response()->json(
            $this->settings->updateTeamMember($this->organizationId(), $memberId, $validated)
        );
    }

    public function revokeInvite(string $inviteId): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        /** @var User $user */
        $user = auth()->user();
        if (! $user->isAdmin()) {
            return $this->forbidden('Only admins can revoke invites.');
        }

        return response()->json(
            $this->settings->revokeInvite($this->organizationId(), $inviteId)
        );
    }

    /**
     * @param  array<string, mixed>  $integrations
     * @return array<string, mixed>
     */
    private function findIntegration(array $integrations, string $providerId): array
    {
        foreach ($integrations['integrations'] ?? [] as $integration) {
            if (($integration['id'] ?? null) === $providerId) {
                return $integration;
            }
        }

        return ['error' => 'Integration not found'];
    }
}
