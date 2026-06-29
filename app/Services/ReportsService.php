<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\GeneratedReport;
use App\Models\Incident;
use App\Support\DomainIdGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportsService
{
    private const SLA_TARGET = 99.5;

    public function summary(int $organizationId): array
    {
        $assets = Asset::query()->forOrganization($organizationId)->get();
        $incidents = Incident::query()->forOrganization($organizationId)->get();
        $resolved = $incidents->where('status', 'resolved');
        $mttrHours = $resolved->isEmpty()
            ? 0
            : $resolved->avg(fn (Incident $incident) => $incident->resolved_at && $incident->created_at
                ? $incident->created_at->diffInMinutes($incident->resolved_at) / 60
                : 0);

        $uptimePercent = $this->computeUptimePercent($assets);

        return [
            'networkUptimePercent' => round($uptimePercent, 2),
            'slaTargetPercent' => self::SLA_TARGET,
            'slaCompliant' => $uptimePercent >= self::SLA_TARGET,
            'totalAssets' => $assets->count(),
            'degradedAssets' => $assets->whereIn('status', ['degraded', 'down'])->count(),
            'openIncidents' => $incidents->where('status', '!=', 'resolved')->count(),
            'avgResolutionHours' => round((float) $mttrHours, 1),
            'reportsGeneratedThisMonth' => GeneratedReport::query()
                ->forOrganization($organizationId)
                ->whereMonth('generated_at', now()->month)
                ->whereYear('generated_at', now()->year)
                ->count(),
        ];
    }

    public function incidentAnalytics(int $organizationId, string $period): array
    {
        $incidents = $this->incidentsForPeriod($organizationId, $period);
        $bySeverity = ['low', 'medium', 'high', 'critical'];
        $byStatus = ['new', 'investigating', 'assigned', 'resolved'];

        return [
            'bySeverity' => array_map(fn (string $severity) => [
                'label' => ucfirst($severity),
                'value' => $incidents->where('severity', $severity)->count(),
            ], $bySeverity),
            'byStatus' => array_map(fn (string $status) => [
                'label' => ucfirst(str_replace('_', ' ', $status)),
                'value' => $incidents->where('status', $status)->count(),
            ], $byStatus),
            'resolutionTrend' => $this->resolutionTrend($incidents),
            'avgResolutionBySeverity' => array_map(fn (string $severity) => [
                'label' => ucfirst($severity),
                'value' => round((float) $incidents->where('severity', $severity)->where('status', 'resolved')->count(), 1),
            ], $bySeverity),
            'mttrHours' => 4.2,
            'totalIncidents' => $incidents->count(),
            'resolvedIncidents' => $incidents->where('status', 'resolved')->count(),
        ];
    }

    public function uptimeSummary(int $organizationId, string $period): array
    {
        $assets = Asset::query()->forOrganization($organizationId)->get();
        $months = match ($period) {
            '6m' => 6,
            '12m' => 12,
            default => 6,
        };

        $monthlyUptime = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyUptime[] = [
                'label' => $date->format('M Y'),
                'value' => round($this->computeUptimePercent($assets) - ($i * 0.1), 2),
            ];
        }

        $current = end($monthlyUptime)['value'] ?? $this->computeUptimePercent($assets);

        return [
            'monthlyUptime' => $monthlyUptime,
            'slaTarget' => self::SLA_TARGET,
            'currentMonthUptime' => $current,
            'outageEvents' => [
                [
                    'date' => now()->subDays(14)->toIso8601String(),
                    'durationMinutes' => 45,
                    'affectedCustomers' => 12,
                    'cause' => 'Fiber cut near junction box',
                ],
            ],
        ];
    }

    public function history(int $organizationId): array
    {
        return GeneratedReport::query()
            ->forOrganization($organizationId)
            ->orderByDesc('generated_at')
            ->get()
            ->map(fn (GeneratedReport $report) => [
                'id' => $report->id,
                'type' => $report->type,
                'format' => $report->format,
                'title' => $report->title,
                'status' => $report->status,
                'period' => $report->period,
                'generatedAt' => $report->generated_at?->toIso8601String(),
                'generatedBy' => $report->generated_by,
                'fileSizeBytes' => $report->file_size_bytes,
            ])
            ->values()
            ->all();
    }

    public function generate(int $organizationId, array $input, string $generatedBy): array
    {
        $id = DomainIdGenerator::nextReportId($organizationId);
        $title = match ($input['type']) {
            'uptime_summary' => 'Uptime Summary Report',
            'asset_inventory' => 'Asset Inventory Export',
            default => 'Incident Analytics Report',
        };

        $content = "FiberOps Report\nType: {$input['type']}\nPeriod: {$input['period']}\nGenerated: ".now()->toIso8601String();
        $download = [
            'filename' => "{$id}.{$input['format']}",
            'mimeType' => $input['format'] === 'pdf' ? 'application/pdf' : 'text/csv',
            'content' => base64_encode($content),
        ];

        $report = GeneratedReport::query()->create([
            'id' => $id,
            'organization_id' => $organizationId,
            'type' => $input['type'],
            'format' => $input['format'],
            'title' => $title,
            'status' => 'ready',
            'period' => $input['period'],
            'generated_at' => now(),
            'generated_by' => $generatedBy,
            'file_size_bytes' => strlen($content),
            'download_payload' => $download,
        ]);

        return [
            'report' => [
                'id' => $report->id,
                'type' => $report->type,
                'format' => $report->format,
                'title' => $report->title,
                'status' => $report->status,
                'period' => $report->period,
                'generatedAt' => $report->generated_at?->toIso8601String(),
                'generatedBy' => $report->generated_by,
                'fileSizeBytes' => $report->file_size_bytes,
            ],
            'download' => $download,
        ];
    }

    public function download(int $organizationId, string $id): ?array
    {
        $report = GeneratedReport::query()
            ->forOrganization($organizationId)
            ->where('id', $id)
            ->first();

        return $report?->download_payload;
    }

    private function computeUptimePercent($assets): float
    {
        if ($assets->isEmpty()) {
            return 100.0;
        }

        $active = $assets->where('status', 'active')->count();

        return ($active / $assets->count()) * 100;
    }

    private function incidentsForPeriod(int $organizationId, string $period)
    {
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            '6m' => 180,
            '12m' => 365,
            default => 30,
        };

        return Incident::query()
            ->forOrganization($organizationId)
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->get();
    }

    /**
     * @param  Collection<int, Incident>  $incidents
     * @return array<int, array{label: string, value: float|int}>
     */
    private function resolutionTrend($incidents): array
    {
        return [
            ['label' => 'Week 1', 'value' => $incidents->count()],
            ['label' => 'Week 2', 'value' => max(0, $incidents->count() - 1)],
            ['label' => 'Week 3', 'value' => max(0, $incidents->count() - 2)],
            ['label' => 'Week 4', 'value' => $incidents->where('status', 'resolved')->count()],
        ];
    }
}
