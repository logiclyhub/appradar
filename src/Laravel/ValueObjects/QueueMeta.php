<?php

namespace AppRadar\Agent\Laravel\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use AppRadar\Agent\Laravel\ValueObjects\Concerns\SerializesValueObject;

class QueueMeta implements Arrayable, JsonSerializable
{
    use SerializesValueObject;

    public function __construct(
        private readonly bool $connected,
        private readonly ?string $connection,
        private readonly ?string $driver,
        private readonly ?string $queue,
        private readonly int $activeWorkers,
        private readonly bool $workerRunning,
        private readonly int $pendingJobs,
        private readonly int $runningJobs,
        private readonly int $staleWaitingJobsOver15Minutes,
        private readonly int $stuckJobsOver1Hour,
        private readonly bool $processedRecently,
        private readonly bool $failingJobsRecently,
        private readonly ?string $message = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->withoutNullValues([
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
