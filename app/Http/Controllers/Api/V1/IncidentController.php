<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsForOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Support\DomainIdGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncidentController extends Controller
{
    use RespondsForOrganization;

    public function index(): JsonResponse
    {
        $incidents = Incident::query()
            ->forOrganization($this->organizationId())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'items' => IncidentResource::collection($incidents),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $incident = $this->findIncident($id);
        if (! $incident) {
            return $this->notFound('Incident not found');
        }

        return response()->json(new IncidentResource($incident));
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'relatedAssetId' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $organizationId = $this->organizationId();
        $incident = Incident::query()->create([
            'id' => DomainIdGenerator::nextIncidentId($organizationId),
            'organization_id' => $organizationId,
            'title' => $validated['title'],
            'severity' => $validated['severity'],
            'status' => 'new',
            'related_asset_id' => $validated['relatedAssetId'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(new IncidentResource($incident), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $incident = $this->findIncident($id);
        if (! $incident) {
            return $this->notFound('Incident not found');
        }

        $body = $request->all();

        if (($body['status'] ?? null) === 'resolved') {
            $validated = $request->validate([
                'status' => ['required', Rule::in(['resolved'])],
                'resolutionNotes' => ['required', 'string', 'min:10'],
            ]);

            $incident->update([
                'status' => 'resolved',
                'resolution_notes' => $validated['resolutionNotes'],
                'resolved_at' => now(),
            ]);

            return response()->json(new IncidentResource($incident->fresh()));
        }

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['new', 'investigating', 'assigned', 'resolved'])],
            'notes' => ['nullable', 'string'],
            'technician' => ['nullable', 'string'],
            'resolutionNotes' => ['nullable', 'string'],
        ]);

        $updates = [];
        foreach (['status', 'notes', 'technician'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updates[$field] = $validated[$field];
            }
        }
        if (array_key_exists('resolutionNotes', $validated)) {
            $updates['resolution_notes'] = $validated['resolutionNotes'];
        }
        if (($validated['status'] ?? null) === 'resolved') {
            $updates['resolved_at'] = now();
        }

        $incident->update($updates);

        return response()->json(new IncidentResource($incident->fresh()));
    }

    private function findIncident(string $id): ?Incident
    {
        return Incident::query()
            ->forOrganization($this->organizationId())
            ->where('id', $id)
            ->first();
    }
}
