<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Core\Contracts\StatusSectionInterface;
use AppRadar\Agent\Data\Concerns\InteractsWithPayload;

class QueueStatus implements StatusSectionInterface
{
    use InteractsWithPayload;

    public function __construct(
        public readonly int $status,
        public readonly bool $connected,
        public readonly ?string $connection,
        public readonly ?string $driver,
        public readonly ?string $queue,
        public readonly ?int $retryAfterSeconds,
        public readonly ?int $blockForSeconds,
        public readonly ?bool $afterCommit,
        public readonly int $activeWorkers,
        public readonly bool $workerRunning,
        public readonly int $pendingJobs,
        public readonly int $runningJobs,
        public readonly int $staleWaitingJobsOver15Minutes,
        public readonly int $stuckJobsOver1Hour,
        public readonly bool $processedRecently,
        public readonly bool $failingJobsRecently,
        public readonly int $completedJobsRecentlyCount,
        public readonly int $failedJobsRecentlyCount,
        public readonly int $exceptionOccurrencesRecently,
        public readonly int $timeoutOccurrencesRecently,
        public readonly int $problemJobsCount,
        /** @var array<int, QueueProblemJob> */
        public readonly array $problemJobs,
        public readonly ?int $workerTimeoutSeconds,
        public readonly ?int $workerSleepSeconds,
        public readonly ?int $workerTries,
        public readonly ?int $workerMemoryMb,
        public readonly ?int $workerBackoffSeconds,
        public readonly ?int $workerMaxTimeSeconds,
        public readonly ?string $workerCommand,
        public readonly ?string $message = null,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function key(): string
    {
        return 'queue';
    }

    public static function label(): string
    {
        return 'Queue';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): static
    {
        return new self(
            status: self::intValue($payload, 'status', 1),
            connected: self::boolValue($payload, 'connected'),
            connection: self::nullableStringValue($payload, 'connection'),
            driver: self::nullableStringValue($payload, 'driver'),
            queue: self::nullableStringValue($payload, 'queue'),
            retryAfterSeconds: self::nullableIntValue($payload, 'retry_after_seconds'),
            blockForSeconds: self::nullableIntValue($payload, 'block_for_seconds'),
            afterCommit: self::nullableBoolValue($payload, 'after_commit'),
            activeWorkers: self::intValue($payload, 'active_workers', 0),
            workerRunning: self::boolValue($payload, 'worker_running'),
            pendingJobs: self::intValue($payload, 'pending_jobs', 0),
            runningJobs: self::intValue($payload, 'running_jobs', 0),
            staleWaitingJobsOver15Minutes: self::intValue($payload, 'stale_waiting_jobs_over_15_minutes', 0),
            stuckJobsOver1Hour: self::intValue($payload, 'stuck_jobs_over_1_hour', 0),
            processedRecently: self::boolValue($payload, 'processed_recently'),
            failingJobsRecently: self::boolValue($payload, 'failing_jobs_recently'),
            completedJobsRecentlyCount: self::intValue($payload, 'completed_jobs_recently_count', 0),
            failedJobsRecentlyCount: self::intValue($payload, 'failed_jobs_recently_count', 0),
            exceptionOccurrencesRecently: self::intValue($payload, 'exception_occurrences_recently', 0),
            timeoutOccurrencesRecently: self::intValue($payload, 'timeout_occurrences_recently', 0),
            problemJobsCount: self::intValue($payload, 'problem_jobs_count', 0),
            problemJobs: collect($payload['problem_jobs'] ?? [])
                ->filter(fn (mixed $job): bool => is_array($job))
                ->map(fn (array $job): QueueProblemJob => QueueProblemJob::fromArray($job))
                ->values()
                ->all(),
            workerTimeoutSeconds: self::nullableIntValue($payload, 'worker_timeout_seconds'),
            workerSleepSeconds: self::nullableIntValue($payload, 'worker_sleep_seconds'),
            workerTries: self::nullableIntValue($payload, 'worker_tries'),
            workerMemoryMb: self::nullableIntValue($payload, 'worker_memory_mb'),
            workerBackoffSeconds: self::nullableIntValue($payload, 'worker_backoff_seconds'),
            workerMaxTimeSeconds: self::nullableIntValue($payload, 'worker_max_time_seconds'),
            workerCommand: self::nullableStringValue($payload, 'worker_command'),
            message: self::nullableStringValue($payload, 'message'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->withoutNullValues([
            'status' => $this->status,
            'connected' => $this->connected,
            'connection' => $this->connection,
            'driver' => $this->driver,
            'queue' => $this->queue,
            'retry_after_seconds' => $this->retryAfterSeconds,
            'block_for_seconds' => $this->blockForSeconds,
            'after_commit' => $this->afterCommit,
            'active_workers' => $this->activeWorkers,
            'worker_running' => $this->workerRunning,
            'pending_jobs' => $this->pendingJobs,
            'running_jobs' => $this->runningJobs,
            'stale_waiting_jobs_over_15_minutes' => $this->staleWaitingJobsOver15Minutes,
            'stuck_jobs_over_1_hour' => $this->stuckJobsOver1Hour,
            'processed_recently' => $this->processedRecently,
            'failing_jobs_recently' => $this->failingJobsRecently,
            'completed_jobs_recently_count' => $this->completedJobsRecentlyCount,
            'failed_jobs_recently_count' => $this->failedJobsRecentlyCount,
            'exception_occurrences_recently' => $this->exceptionOccurrencesRecently,
            'timeout_occurrences_recently' => $this->timeoutOccurrencesRecently,
            'problem_jobs_count' => $this->problemJobsCount,
            'problem_jobs' => array_map(
                static fn (QueueProblemJob $job): array => $job->toArray(),
                $this->problemJobs,
            ),
            'worker_timeout_seconds' => $this->workerTimeoutSeconds,
            'worker_sleep_seconds' => $this->workerSleepSeconds,
            'worker_tries' => $this->workerTries,
            'worker_memory_mb' => $this->workerMemoryMb,
            'worker_backoff_seconds' => $this->workerBackoffSeconds,
            'worker_max_time_seconds' => $this->workerMaxTimeSeconds,
            'worker_command' => $this->workerCommand,
            'message' => $this->message,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
