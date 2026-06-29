<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsForOrganization;
use App\Http\Controllers\Controller;
use App\Models\PlanningProposal;
use App\Support\DomainIdGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanningProposalController extends Controller
{
    use RespondsForOrganization;

    public function index(): JsonResponse
    {
        $proposals = PlanningProposal::query()
            ->forOrganization($this->organizationId())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PlanningProposal $proposal) => $this->toPublic($proposal));

        return response()->json(['items' => $proposals]);
    }

    public function show(string $id): JsonResponse
    {
        $proposal = $this->findProposal($id);
        if (! $proposal) {
            return $this->notFound('Planning proposal not found');
        }

        return response()->json($this->toPublic($proposal));
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $validated = $this->validateProposal($request, true);
        $organizationId = $this->organizationId();
        $now = now()->toIso8601String();
        $id = DomainIdGenerator::nextProposalId($organizationId);

        $payload = array_merge($validated, [
            'id' => $id,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);

        $proposal = PlanningProposal::query()->create([
            'id' => $id,
            'organization_id' => $organizationId,
            'payload' => $payload,
        ]);

        return response()->json($this->toPublic($proposal), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $proposal = $this->findProposal($id);
        if (! $proposal) {
            return $this->notFound('Planning proposal not found');
        }

        $validated = $this->validateProposal($request, false);
        $payload = array_merge($proposal->payload ?? [], $validated, [
            'updatedAt' => now()->toIso8601String(),
        ]);

        $proposal->update(['payload' => $payload]);

        return response()->json($this->toPublic($proposal->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProposal(Request $request, bool $creating): array
    {
        $rules = [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'min:3'],
            'description' => ['nullable', 'string'],
            'type' => [$creating ? 'required' : 'sometimes', Rule::in(['fiber_expansion', 'splitter_upgrade', 'pop_build', 'capacity_upgrade', 'new_market'])],
            'status' => ['sometimes', Rule::in(['draft', 'review', 'approved', 'in_progress', 'completed', 'cancelled'])],
            'targetArea' => [$creating ? 'required' : 'sometimes', 'string'],
            'relatedAssetId' => ['nullable', 'string'],
            'estimatedNewCustomers' => [$creating ? 'required' : 'sometimes', 'integer', 'min:0'],
            'currentUtilizationPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'projectedUtilizationPercent' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0', 'max:100'],
            'estimatedBudgetUsd' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'budgetLineItems' => ['sometimes', 'array'],
            'areas' => ['sometimes', 'array'],
            'routes' => ['sometimes', 'array'],
            'owner' => [$creating ? 'required' : 'sometimes', 'string'],
            'targetStartDate' => ['nullable', 'string'],
            'targetCompletionDate' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];

        return $request->validate($rules);
    }

    /**
     * @return array<string, mixed>
     */
    private function toPublic(PlanningProposal $proposal): array
    {
        return $proposal->payload ?? [];
    }

    private function findProposal(string $id): ?PlanningProposal
    {
        return PlanningProposal::query()
            ->forOrganization($this->organizationId())
            ->where('id', $id)
            ->first();
    }
}
