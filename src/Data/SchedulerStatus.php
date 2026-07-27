<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Core\Contracts\StatusSectionInterface;
use AppRadar\Agent\Data\Concerns\InteractsWithPayload;
use Carbon\CarbonImmutable;

class SchedulerStatus implements StatusSectionInterface
{
    use InteractsWithPayload;

    public function __construct(
        public readonly int $status,
        public readonly bool $running,
        public readonly ?CarbonImmutable $lastHeartbeatAt,
        public readonly ?int $lastSuccessfulRunSecondsAgo,
        public readonly int $expectedIntervalSeconds,
        public readonly int $registeredCrons,
        public readonly int $failedCronsRecently,
        public readonly int $successfulCronsRecently,
        public readonly int $runningCrons,
        public readonly int $slowCrons,
        public readonly ?string $message = null,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function key(): string
    {
        return 'scheduler';
    }

    public static function label(): string
    {
        return 'Scheduler';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): static
    {
        return new self(
            status: self::intValue($payload, 'status', 1),
            running: self::boolValue($payload, 'running'),
            lastHeartbeatAt: self::timestampValue($payload, 'last_heartbeat_at'),
            lastSuccessfulRunSecondsAgo: self::nullableIntValue($payload, 'last_successful_run_seconds_ago'),
            expectedIntervalSeconds: self::intValue($payload, 'expected_interval_seconds', 60),
            registeredCrons: self::intValue($payload, 'registered_crons', 0),
            failedCronsRecently: self::intValue($payload, 'failed_crons_recently', 0),
            successfulCronsRecently: self::intValue($payload, 'successful_crons_recently', 0),
            runningCrons: self::intValue($payload, 'running_crons', 0),
            slowCrons: self::intValue($payload, 'slow_crons', 0),
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
            'running' => $this->running,
            'last_heartbeat_at' => self::timestampString($this->lastHeartbeatAt),
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
