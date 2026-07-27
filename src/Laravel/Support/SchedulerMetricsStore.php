<?php

namespace AppRadar\Agent\Laravel\Support;

use Carbon\Carbon;

class SchedulerMetricsStore
{
    private const FILE = 'scheduler-metrics.json';

    private const RETENTION_SECONDS = 604800;

    public function __construct(
        private readonly StatusFileStore $store = new StatusFileStore(),
    ) {
    }

    public function markStarted(SchedulerTaskDescriptor $task): void
    {
        $this->store->updateJson(self::FILE, function (array $payload) use ($task): array {
            $state = $this->normalize($payload);
            $state['running_tasks'][$task->key] = [
                'task_name' => $task->name,
                'started_at' => now()->toIso8601String(),
            ];

            return $this->trim($state);
        });
    }

    public function markFinished(SchedulerTaskDescriptor $task, ?float $runtimeSeconds = null): void
    {
        $this->store->updateJson(self::FILE, function (array $payload) use ($task, $runtimeSeconds): array {
            $state = $this->normalize($payload);
            $startedAt = $state['running_tasks'][$task->key]['started_at'] ?? null;
            unset($state['running_tasks'][$task->key]);
            $state['recent_successes'][] = [
                'task_name' => $task->name,
                'finished_at' => now()->toIso8601String(),
                'runtime_seconds' => $runtimeSeconds ?? $this->runtimeFromStart($startedAt),
            ];

            return $this->trim($state);
        });
    }

    public function markFailed(SchedulerTaskDescriptor $task, ?string $message = null): void
    {
        $this->store->updateJson(self::FILE, function (array $payload) use ($task, $message): array {
            $state = $this->normalize($payload);
            unset($state['running_tasks'][$task->key]);
            $state['recent_failures'][] = [
                'task_name' => $task->name,
                'failed_at' => now()->toIso8601String(),
                'message' => $message,
            ];

            return $this->trim($state);
        });
    }

    /**
     * @return array{successful_crons_recently:int,failed_crons_recently:int,running_crons:int,slow_crons:int}
     */
    public function snapshot(int $recentWindowSeconds = 86400, int $slowThresholdSeconds = 300): array
    {
        $state = $this->normalize($this->store->readJson(self::FILE) ?? []);
        $cutoff = now()->subSeconds($recentWindowSeconds);

        $recentSuccesses = collect($state['recent_successes'])
            ->filter(fn (array $entry): bool => $this->isAfter($entry['finished_at'] ?? null, $cutoff));
        $recentFailures = collect($state['recent_failures'])
            ->filter(fn (array $entry): bool => $this->isAfter($entry['failed_at'] ?? null, $cutoff));
        $runningTasks = collect($state['running_tasks'])
            ->filter(fn (array $entry): bool => isset($entry['started_at']));
        $slowTasks = $runningTasks->filter(function (array $entry) use ($slowThresholdSeconds): bool {
            return Carbon::parse((string) $entry['started_at'])->diffInSeconds(now()) > $slowThresholdSeconds;
        });

        return [
            'successful_crons_recently' => $recentSuccesses->count(),
            'failed_crons_recently' => $recentFailures->count(),
            'running_crons' => $runningTasks->count(),
            'slow_crons' => $slowTasks->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{running_tasks:array<string,array<string,mixed>>,recent_successes:array<int,array<string,mixed>>,recent_failures:array<int,array<string,mixed>>}
     */
    private function normalize(array $payload): array
    {
        return [
            'running_tasks' => is_array($payload['running_tasks'] ?? null) ? $payload['running_tasks'] : [],
            'recent_successes' => is_array($payload['recent_successes'] ?? null) ? array_values($payload['recent_successes']) : [],
            'recent_failures' => is_array($payload['recent_failures'] ?? null) ? array_values($payload['recent_failures']) : [],
        ];
    }

    /**
     * @param  array{running_tasks:array<string,array<string,mixed>>,recent_successes:array<int,array<string,mixed>>,recent_failures:array<int,array<string,mixed>>}  $state
     * @return array{running_tasks:array<string,array<string,mixed>>,recent_successes:array<int,array<string,mixed>>,recent_failures:array<int,array<string,mixed>>}
     */
    private function trim(array $state): array
    {
        $cutoff = now()->subSeconds(self::RETENTION_SECONDS);
        $state['recent_successes'] = array_values(array_filter(
            $state['recent_successes'],
            fn (array $entry): bool => $this->isAfter($entry['finished_at'] ?? null, $cutoff),
        ));
        $state['recent_failures'] = array_values(array_filter(
            $state['recent_failures'],
            fn (array $entry): bool => $this->isAfter($entry['failed_at'] ?? null, $cutoff),
        ));

        return $state;
    }

    private function runtimeFromStart(?string $startedAt): ?float
    {
        if ($startedAt === null) {
            return null;
        }

        return round(Carbon::parse($startedAt)->diffInMilliseconds(now()) / 1000, 2);
    }

    private function isAfter(?string $timestamp, Carbon $cutoff): bool
    {
        return $timestamp !== null && Carbon::parse($timestamp)->greaterThanOrEqualTo($cutoff);
    }
}
