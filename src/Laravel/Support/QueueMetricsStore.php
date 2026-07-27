<?php

namespace AppRadar\Agent\Laravel\Support;

use Carbon\Carbon;

class QueueMetricsStore
{
    private const FILE = 'queue-metrics.json';

    public function __construct(
        private readonly StatusFileStore $store = new StatusFileStore(),
    ) {
    }

    public function markProcessed(string $connection, string $queue): void
    {
        $this->update($connection, $queue, function (array $metrics): array {
            $metrics['last_processed_at'] = now()->toIso8601String();

            return $metrics;
        });
    }

    public function markFailed(string $connection, string $queue): void
    {
        $this->update($connection, $queue, function (array $metrics): array {
            $metrics['last_failed_at'] = now()->toIso8601String();

            return $metrics;
        });
    }

    /**
     * @return array{processed_recently:bool,failing_jobs_recently:bool}
     */
    public function snapshot(
        string $connection,
        string $queue,
        int $recentWindowSeconds,
        bool $defaultProcessedRecently,
    ): array {
        $payload = $this->store->readJson(self::FILE) ?? [];
        $queues = is_array($payload['queues'] ?? null) ? $payload['queues'] : [];
        $metrics = $queues[$this->key($connection, $queue)] ?? [];
        $cutoff = now()->subSeconds($recentWindowSeconds);

        return [
            'processed_recently' => $defaultProcessedRecently || $this->isAfter($metrics['last_processed_at'] ?? null, $cutoff),
            'failing_jobs_recently' => $this->isAfter($metrics['last_failed_at'] ?? null, $cutoff),
        ];
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $updater
     */
    private function update(string $connection, string $queue, callable $updater): void
    {
        $key = $this->key($connection, $queue);

        $this->store->updateJson(self::FILE, function (array $payload) use ($key, $updater): array {
            $queues = is_array($payload['queues'] ?? null) ? $payload['queues'] : [];
            $current = is_array($queues[$key] ?? null) ? $queues[$key] : [];
            $queues[$key] = $updater($current);

            return ['queues' => $queues];
        });
    }

    private function key(string $connection, string $queue): string
    {
        return $connection.':'.$queue;
    }

    private function isAfter(?string $timestamp, Carbon $cutoff): bool
    {
        return $timestamp !== null && Carbon::parse($timestamp)->greaterThanOrEqualTo($cutoff);
    }
}
