<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Core\Contracts\StatusSectionInterface;
use AppRadar\Agent\Data\Concerns\InteractsWithPayload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class StatusReport implements Arrayable, JsonSerializable
{
    use InteractsWithPayload;

    public function __construct(
        public readonly string $name,
        public readonly string $environment,
        public readonly int $status,
        public readonly CarbonImmutable $checkedAt,
        public readonly DatabaseStatus $database,
        public readonly RedisStatus $redis,
        public readonly SchedulerStatus $scheduler,
        public readonly QueueStatus $queue,
        public readonly TestsStatus $tests,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: self::nullableStringValue($payload, 'name') ?? 'unknown',
            environment: self::nullableStringValue($payload, 'environment') ?? 'unknown',
            status: self::intValue($payload, 'status', 1),
            checkedAt: self::timestampValue($payload, 'checked_at') ?? CarbonImmutable::now(),
            database: DatabaseStatus::fromArray(is_array($payload['database'] ?? null) ? $payload['database'] : []),
            redis: RedisStatus::fromArray(is_array($payload['redis'] ?? null) ? $payload['redis'] : []),
            scheduler: SchedulerStatus::fromArray(is_array($payload['scheduler'] ?? null) ? $payload['scheduler'] : []),
            queue: QueueStatus::fromArray(is_array($payload['queue'] ?? null) ? $payload['queue'] : []),
            tests: TestsStatus::fromArray(is_array($payload['tests'] ?? null) ? $payload['tests'] : []),
        );
    }

    /**
     * @return array<string, StatusSectionInterface>
     */
    public function sections(): array
    {
        return [
            DatabaseStatus::key() => $this->database,
            RedisStatus::key() => $this->redis,
            SchedulerStatus::key() => $this->scheduler,
            QueueStatus::key() => $this->queue,
            TestsStatus::key() => $this->tests,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'environment' => $this->environment,
            'status' => $this->status,
            'checked_at' => $this->checkedAt->toIso8601String(),
            'database' => $this->database->toArray(),
            'redis' => $this->redis->toArray(),
            'scheduler' => $this->scheduler->toArray(),
            'queue' => $this->queue->toArray(),
            'tests' => $this->tests->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
