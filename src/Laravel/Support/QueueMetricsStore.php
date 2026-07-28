<?php

namespace AppRadar\Agent\Laravel\Support;

use Carbon\Carbon;
use Illuminate\Support\Arr;

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

    public function recordIncident(
        string $connection,
        string $queue,
        string $jobName,
        ?int $attempt,
        ?string $exceptionClass,
        ?string $exceptionMessage,
        bool $timedOut,
        bool $failed,
    ): void {
        $now = now();
        $retentionCutoff = $now->copy()->subHours((int) config('appradar.queue.incident_retention_hours', 24));
        $event = [
            'at' => $now->toIso8601String(),
            'attempt' => $attempt,
            'timeout' => $timedOut,
            'failed' => $failed,
            'exception_class' => $exceptionClass,
            'exception_message' => $exceptionMessage,
        ];

        $this->update($connection, $queue, function (array $metrics) use ($jobName, $event, $retentionCutoff): array {
            $metrics['last_failed_at'] = now()->toIso8601String();
            $jobs = is_array($metrics['jobs'] ?? null) ? $metrics['jobs'] : [];
            $key = $this->jobKey($jobName);
            $job = is_array($jobs[$key] ?? null) ? $jobs[$key] : [];

            $events = collect(is_array($job['events'] ?? null) ? $job['events'] : [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->filter(fn (array $item): bool => $this->isAfter($item['at'] ?? null, $retentionCutoff))
                ->values();

            $events->push($event);

            $jobs[$key] = [
                'name' => $jobName,
                'first_seen_at' => $job['first_seen_at'] ?? $event['at'],
                'last_seen_at' => $event['at'],
                'events' => $events->take(-50)->values()->all(),
            ];

            $metrics['jobs'] = $jobs;

            return $metrics;
        });
    }

    /**
     * @return array{
     *     processed_recently:bool,
     *     failing_jobs_recently:bool,
     *     exception_occurrences_recently:int,
     *     timeout_occurrences_recently:int,
     *     problem_jobs_count:int,
     *     problem_jobs:array<int, array<string, mixed>>
     * }
     */
    public function snapshot(
        string $connection,
        string $queue,
        int $activityWindowSeconds,
        int $problemWindowSeconds,
        bool $defaultProcessedRecently,
    ): array {
        $payload = $this->store->readJson(self::FILE) ?? [];
        $queues = is_array($payload['queues'] ?? null) ? $payload['queues'] : [];
        $metrics = $queues[$this->key($connection, $queue)] ?? [];
        $activityCutoff = now()->subSeconds($activityWindowSeconds);
        $problemCutoff = now()->subSeconds($problemWindowSeconds);
        $problemThreshold = (int) config('appradar.queue.problem_threshold', 3);
        $timeoutThreshold = (int) config('appradar.queue.timeout_threshold', 2);
        $maxProblemJobs = (int) config('appradar.queue.max_problem_jobs', 5);

        $jobSummaries = collect(is_array($metrics['jobs'] ?? null) ? $metrics['jobs'] : [])
            ->filter(fn (mixed $job): bool => is_array($job))
            ->map(function (array $job) use ($problemCutoff): ?array {
                $events = collect(is_array($job['events'] ?? null) ? $job['events'] : [])
                    ->filter(fn (mixed $event): bool => is_array($event))
                    ->filter(fn (array $event): bool => $this->isAfter($event['at'] ?? null, $problemCutoff))
                    ->values();

                if ($events->isEmpty()) {
                    return null;
                }

                $latest = $events->last();
                $timeoutOccurrences = $events->where('timeout', true)->count();
                $failedOccurrences = $events->where('failed', true)->count();

                return [
                    'name' => Arr::get($job, 'name', 'Unknown Job'),
                    'occurrences' => $events->count(),
                    'failed_occurrences' => $failedOccurrences,
                    'timeout_occurrences' => $timeoutOccurrences,
                    'max_attempts_seen' => (int) $events
                        ->pluck('attempt')
                        ->filter(fn (mixed $attempt): bool => is_numeric($attempt))
                        ->max(),
                    'first_seen_at' => Arr::get($job, 'first_seen_at'),
                    'last_seen_at' => Arr::get($job, 'last_seen_at'),
                    'latest_exception_class' => Arr::get($latest, 'exception_class'),
                    'latest_exception_message' => Arr::get($latest, 'exception_message'),
                ];
            })
            ->filter()
            ->values();

        $problemJobs = $jobSummaries
            ->filter(fn (array $job): bool => $job['occurrences'] >= $problemThreshold || $job['timeout_occurrences'] >= $timeoutThreshold)
            ->sort(function (array $left, array $right): int {
                return [$right['timeout_occurrences'], $right['occurrences'], $right['max_attempts_seen']]
                    <=> [$left['timeout_occurrences'], $left['occurrences'], $left['max_attempts_seen']];
            })
            ->take($maxProblemJobs)
            ->values();

        return [
            'processed_recently' => $defaultProcessedRecently || $this->isAfter($metrics['last_processed_at'] ?? null, $activityCutoff),
            'failing_jobs_recently' => $this->isAfter($metrics['last_failed_at'] ?? null, $activityCutoff),
            'exception_occurrences_recently' => $jobSummaries->sum('occurrences'),
            'timeout_occurrences_recently' => $jobSummaries->sum('timeout_occurrences'),
            'problem_jobs_count' => $problemJobs->count(),
            'problem_jobs' => $problemJobs->all(),
        ];
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $updater
     */
    private function update(string $connection, string $queue, callable $updater): void
    {
        $key = $this->key($connection, $queue);
        $retentionCutoff = now()->subHours((int) config('appradar.queue.incident_retention_hours', 24));

        $this->store->updateJson(self::FILE, function (array $payload) use ($key, $updater, $retentionCutoff): array {
            $queues = is_array($payload['queues'] ?? null) ? $payload['queues'] : [];
            $current = is_array($queues[$key] ?? null) ? $queues[$key] : [];
            $updated = $updater($current);
            $updated['jobs'] = $this->pruneJobs($updated['jobs'] ?? [], $retentionCutoff);
            $queues[$key] = $updated;

            return ['queues' => $queues];
        });
    }

    private function key(string $connection, string $queue): string
    {
        return $connection.':'.$queue;
    }

    private function jobKey(string $jobName): string
    {
        return sha1($jobName);
    }

    /**
     * @param  array<string, mixed>  $jobs
     * @return array<string, mixed>
     */
    private function pruneJobs(array $jobs, Carbon $cutoff): array
    {
        return collect($jobs)
            ->filter(fn (mixed $job): bool => is_array($job))
            ->map(function (array $job) use ($cutoff): ?array {
                $events = collect(is_array($job['events'] ?? null) ? $job['events'] : [])
                    ->filter(fn (mixed $event): bool => is_array($event))
                    ->filter(fn (array $event): bool => $this->isAfter($event['at'] ?? null, $cutoff))
                    ->take(-50)
                    ->values()
                    ->all();

                if ($events === []) {
                    return null;
                }

                $lastSeenAt = end($events)['at'] ?? ($job['last_seen_at'] ?? null);
                $firstSeenAt = $job['first_seen_at'] ?? ($events[0]['at'] ?? null);

                return [
                    'name' => $job['name'] ?? 'Unknown Job',
                    'first_seen_at' => $firstSeenAt,
                    'last_seen_at' => $lastSeenAt,
                    'events' => $events,
                ];
            })
            ->filter()
            ->all();
    }

    private function isAfter(?string $timestamp, Carbon $cutoff): bool
    {
        return $timestamp !== null && Carbon::parse($timestamp)->greaterThanOrEqualTo($cutoff);
    }
}
