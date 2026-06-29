<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsForOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Support\DomainIdGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    use RespondsForOrganization;

    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()
            ->forOrganization($this->organizationId())
            ->orderBy('name');

        if ($request->filled('limit')) {
            $query->limit((int) $request->integer('limit'));
        }

        if ($request->filled('offset')) {
            $query->offset((int) $request->integer('offset'));
        }

        $assets = $query->get();

        return response()->json([
            'items' => AssetResource::collection($assets),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2'],
            'kind' => ['required', Rule::in(['pole', 'junction_box', 'splitter', 'onu', 'pop', 'fiber_route'])],
            'status' => ['required', Rule::in(['active', 'degraded', 'down', 'maintenance'])],
            'location.lat' => ['required', 'numeric', 'between:-90,90'],
            'location.lng' => ['required', 'numeric', 'between:-180,180'],
            'monitorHost' => ['nullable', 'string', 'max:255'],
        ]);

        $organizationId = $this->organizationId();
        $asset = Asset::query()->create([
            'id' => DomainIdGenerator::nextAssetId($organizationId, $validated['kind'], $validated['name']),
            'organization_id' => $organizationId,
            'kind' => $validated['kind'],
            'name' => $validated['name'],
            'status' => $validated['status'],
            'location_lat' => $validated['location']['lat'],
            'location_lng' => $validated['location']['lng'],
            'monitor_host' => $validated['monitorHost'] ?? null,
        ]);

        return response()->json(new AssetResource($asset), 201);
    }
}
