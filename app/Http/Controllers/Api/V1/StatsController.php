<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsForOrganization;
use App\Http\Controllers\Controller;
use App\Services\InterfaceStatsService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    use RespondsForOrganization;

    public function __construct(private readonly InterfaceStatsService $interfaceStats) {}

    public function usage(): JsonResponse
    {
        return response()->json(
            $this->interfaceStats->getUsageChart($this->organizationId())
        );
    }
}
