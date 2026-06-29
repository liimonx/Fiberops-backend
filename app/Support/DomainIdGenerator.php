<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\Incident;
use App\Models\PlanningProposal;
use App\Models\WorkOrder;

class DomainIdGenerator
{
    /** @var array<string, string> */
    private const KIND_PREFIXES = [
        'pole' => 'pole',
        'junction_box' => 'jb',
        'splitter' => 'split',
        'onu' => 'onu',
        'pop' => 'pop',
        'fiber_route' => 'fiber-route',
    ];

    public static function nextAssetId(int $organizationId, string $kind, string $name): string
    {
        $prefix = self::KIND_PREFIXES[$kind] ?? 'asset';
        $slug = self::slugify($name);

        if ($kind === 'fiber_route') {
            $count = Asset::query()
                ->forOrganization($organizationId)
                ->where('kind', 'fiber_route')
                ->count();

            return sprintf('%s-%03d', $prefix, $count + 1);
        }

        $pattern = "{$prefix}-{$slug}-%";
        $matching = Asset::query()
            ->forOrganization($organizationId)
            ->where('id', 'like', $pattern)
            ->count();

        return sprintf('%s-%s-%02d', $prefix, $slug, $matching + 1);
    }

    public static function nextCustomerId(int $organizationId): string
    {
        $count = Customer::query()->forOrganization($organizationId)->count();

        return sprintf('cust-%03d', $count + 1);
    }

    public static function nextIncidentId(int $organizationId): string
    {
        $count = Incident::query()->forOrganization($organizationId)->count();

        return sprintf('inc-%03d', $count + 1);
    }

    public static function nextWorkOrderId(int $organizationId): string
    {
        $count = WorkOrder::query()->forOrganization($organizationId)->count();

        return sprintf('wo-%03d', $count + 1);
    }

    public static function nextProposalId(int $organizationId): string
    {
        $count = PlanningProposal::query()->forOrganization($organizationId)->count();

        return sprintf('prop-%03d', $count + 1);
    }

    public static function nextReportId(int $organizationId): string
    {
        $reports = GeneratedReport::query()->forOrganization($organizationId)->pluck('id');
        $max = 0;
        foreach ($reports as $id) {
            if (preg_match('/rpt-(\d+)/', $id, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return sprintf('rpt-%03d', $max + 1);
    }

    private static function slugify(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '');
        $slug = trim($slug, '-');

        return substr($slug ?: 'asset', 0, 24);
    }
}
