<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsForOrganization;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportsController extends Controller
{
    use RespondsForOrganization;

    public function __construct(private readonly ReportsService $reports) {}

    public function summary(): JsonResponse
    {
        return response()->json($this->reports->summary($this->organizationId()));
    }

    public function incidentAnalytics(Request $request): JsonResponse
    {
        $period = $request->query('period', '30d');

        return response()->json($this->reports->incidentAnalytics($this->organizationId(), $period));
    }

    public function uptime(Request $request): JsonResponse
    {
        $period = $request->query('period', '6m');

        return response()->json($this->reports->uptimeSummary($this->organizationId(), $period));
    }

    public function history(): JsonResponse
    {
        return response()->json(['items' => $this->reports->history($this->organizationId())]);
    }

    public function generate(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['uptime_summary', 'asset_inventory', 'incident_analytics'])],
            'format' => ['required', Rule::in(['pdf', 'csv'])],
            'period' => ['required', Rule::in(['7d', '30d', '90d', '6m', '12m'])],
        ]);

        /** @var User $user */
        $user = auth()->user();

        return response()->json(
            $this->reports->generate($this->organizationId(), $validated, $user->name),
            201
        );
    }

    public function download(string $id): JsonResponse
    {
        $payload = $this->reports->download($this->organizationId(), $id);
        if (! $payload) {
            return $this->notFound('Report not found');
        }

        return response()->json($payload);
    }
}
