<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class InterfaceStatsService
{
    private const SAMPLE_TTL_SECONDS = 90000;

    /**
     * @return list<array{label: string, value: float}>
     */
    public function getUsageChart(int $organizationId): array
    {
        $samples = $this->getSamples($organizationId);

        if ($samples === []) {
            return $this->emptyChart();
        }

        return collect($samples)
            ->map(fn (array $sample) => [
                'label' => $sample['label'],
                'value' => round((float) $sample['value'], 1),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{rxBytes: int, txBytes: int}  $stats
     */
    public function recordSample(int $organizationId, array $stats): float
    {
        $key = $this->samplesKey($organizationId);
        $now = now();
        $previous = Redis::hget($key, 'last');

        $mbps = 0.0;

        if (is_string($previous)) {
            $decoded = json_decode($previous, true);

            if (is_array($decoded)) {
                $elapsed = max(1, $now->timestamp - (int) ($decoded['timestamp'] ?? $now->timestamp));
                $deltaBytes = max(
                    0,
                    ($stats['rxBytes'] + $stats['txBytes']) - ((int) ($decoded['totalBytes'] ?? 0))
                );
                $mbps = ($deltaBytes * 8) / ($elapsed * 1_000_000);
            }
        }

        $sample = [
            'label' => $now->format('H:i'),
            'value' => round($mbps, 1),
            'timestamp' => $now->timestamp,
            'totalBytes' => $stats['rxBytes'] + $stats['txBytes'],
        ];

        Redis::rpush($this->historyKey($organizationId), json_encode($sample, JSON_THROW_ON_ERROR));
        Redis::ltrim($this->historyKey($organizationId), -288, -1);
        Redis::expire($this->historyKey($organizationId), self::SAMPLE_TTL_SECONDS);
        Redis::hset($key, 'last', json_encode($sample, JSON_THROW_ON_ERROR));
        Redis::expire($key, self::SAMPLE_TTL_SECONDS);

        return round($mbps, 1);
    }

    /**
     * @return list<array{label: string, value: float, timestamp: int}>
     */
    private function getSamples(int $organizationId): array
    {
        $raw = Redis::lrange($this->historyKey($organizationId), -24, -1);

        return collect($raw)
            ->map(function (string $entry): ?array {
                $decoded = json_decode($entry, true);

                if (! is_array($decoded)) {
                    return null;
                }

                return [
                    'label' => (string) ($decoded['label'] ?? ''),
                    'value' => (float) ($decoded['value'] ?? 0),
                    'timestamp' => (int) ($decoded['timestamp'] ?? 0),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    private function emptyChart(): array
    {
        return [
            ['label' => '00:00', 'value' => 0],
            ['label' => '06:00', 'value' => 0],
            ['label' => '12:00', 'value' => 0],
            ['label' => '18:00', 'value' => 0],
            ['label' => '23:59', 'value' => 0],
        ];
    }

    private function samplesKey(int $organizationId): string
    {
        return "org:{$organizationId}:mikrotik:interface:last";
    }

    private function historyKey(int $organizationId): string
    {
        return "org:{$organizationId}:mikrotik:interface:history";
    }
}
