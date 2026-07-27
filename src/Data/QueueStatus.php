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
        public readonly int $activeWorkers,
        public readonly bool $workerRunning,
        public readonly int $pendingJobs,
        public readonly int $runningJobs,
        public readonly int $staleWaitingJobsOver15Minutes,
        public readonly int $stuckJobsOver1Hour,
        public readonly bool $processedRecently,
        public readonly bool $failingJobsRecently,
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
            activeWorkers: self::intValue($payload, 'active_workers', 0),
            workerRunning: self::boolValue($payload, 'worker_running'),
            pendingJobs: self::intValue($payload, 'pending_jobs', 0),
            runningJobs: self::intValue($payload, 'running_jobs', 0),
            staleWaitingJobsOver15Minutes: self::intValue($payload, 'stale_waiting_jobs_over_15_minutes', 0),
            stuckJobsOver1Hour: self::intValue($payload, 'stuck_jobs_over_1_hour', 0),
            processedRecently: self::boolValue($payload, 'processed_recently'),
            failingJobsRecently: self::boolValue($payload, 'failing_jobs_recently'),
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
            'active_workers' => $this->activeWorkers,
            'worker_running' => $this->workerRunning,
            'pending_jobs' => $this->pendingJobs,
            'running_jobs' => $this->runningJobs,
            'stale_waiting_jobs_over_15_minutes' => $this->staleWaitingJobsOver15Minutes,
            'stuck_jobs_over_1_hour' => $this->stuckJobsOver1Hour,
            'processed_recently' => $this->processedRecently,
            'failing_jobs_recently' => $this->failingJobsRecently,
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
