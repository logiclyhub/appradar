<?php

namespace AppRadar\Agent\Laravel\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use AppRadar\Agent\Laravel\ValueObjects\Concerns\SerializesValueObject;

class SchedulerMeta implements Arrayable, JsonSerializable
{
    use SerializesValueObject;

    public function __construct(
        private readonly bool $running,
        private readonly ?string $lastHeartbeatAt,
        private readonly ?int $lastSuccessfulRunSecondsAgo,
        private readonly int $expectedIntervalSeconds,
        private readonly int $registeredCrons,
        private readonly int $failedCronsRecently,
        private readonly int $successfulCronsRecently,
        private readonly int $runningCrons,
        private readonly int $slowCrons,
        private readonly ?string $message = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->withoutNullValues([
            'running' => $this->running,
            'last_heartbeat_at' => $this->lastHeartbeatAt,
            'last_successful_run_seconds_ago' => $this->lastSuccessfulRunSecondsAgo,
            'expected_interval_seconds' => $this->expectedIntervalSeconds,
            'registered_crons' => $this->registeredCrons,
            'failed_crons_recently' => $this->failedCronsRecently,
            'successful_crons_recently' => $this->successfulCronsRecently,
            'running_crons' => $this->runningCrons,
            'slow_crons' => $this->slowCrons,
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
