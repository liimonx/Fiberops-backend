<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsForOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use App\Support\DomainIdGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    use RespondsForOrganization;

    public function index(): JsonResponse
    {
        $orders = WorkOrder::query()
            ->forOrganization($this->organizationId())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'items' => WorkOrderResource::collection($orders),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $order = $this->findWorkOrder($id);
        if (! $order) {
            return $this->notFound('Work order not found');
        }

        return response()->json(new WorkOrderResource($order));
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'workType' => ['required', Rule::in(['survey', 'audit', 'repair', 'upgrade', 'install', 'setup'])],
            'assigneeId' => ['nullable', 'string'],
            'relatedIncidentId' => ['nullable', 'string'],
            'relatedAssetId' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $organizationId = $this->organizationId();
        $order = WorkOrder::query()->create([
            'id' => DomainIdGenerator::nextWorkOrderId($organizationId),
            'organization_id' => $organizationId,
            'title' => $validated['title'],
            'priority' => $validated['priority'],
            'work_type' => $validated['workType'],
            'status' => 'new',
            'assignee_id' => $validated['assigneeId'] ?? null,
            'related_incident_id' => $validated['relatedIncidentId'] ?? null,
            'related_asset_id' => $validated['relatedAssetId'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(new WorkOrderResource($order), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $order = $this->findWorkOrder($id);
        if (! $order) {
            return $this->notFound('Work order not found');
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'min:3'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'workType' => ['sometimes', Rule::in(['survey', 'audit', 'repair', 'upgrade', 'install', 'setup'])],
            'status' => ['sometimes', Rule::in(['new', 'assigned', 'in_progress', 'review', 'done'])],
            'assigneeId' => ['nullable', 'string'],
            'relatedIncidentId' => ['nullable', 'string'],
            'relatedAssetId' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $updates = [];
        if (array_key_exists('title', $validated)) {
            $updates['title'] = $validated['title'];
        }
        if (array_key_exists('priority', $validated)) {
            $updates['priority'] = $validated['priority'];
        }
        if (array_key_exists('workType', $validated)) {
            $updates['work_type'] = $validated['workType'];
        }
        if (array_key_exists('status', $validated)) {
            $updates['status'] = $validated['status'];
        }
        if (array_key_exists('assigneeId', $validated)) {
            $updates['assignee_id'] = $validated['assigneeId'];
        }
        if (array_key_exists('relatedIncidentId', $validated)) {
            $updates['related_incident_id'] = $validated['relatedIncidentId'];
        }
        if (array_key_exists('relatedAssetId', $validated)) {
            $updates['related_asset_id'] = $validated['relatedAssetId'];
        }
        if (array_key_exists('notes', $validated)) {
            $updates['notes'] = $validated['notes'];
        }

        $order->update($updates);

        return response()->json(new WorkOrderResource($order->fresh()));
    }

    private function findWorkOrder(string $id): ?WorkOrder
    {
        return WorkOrder::query()
            ->forOrganization($this->organizationId())
            ->where('id', $id)
            ->first();
    }
}
