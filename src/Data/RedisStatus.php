<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Core\Contracts\StatusSectionInterface;
use AppRadar\Agent\Data\Concerns\InteractsWithPayload;

class RedisStatus implements StatusSectionInterface
{
    use InteractsWithPayload;

    public function __construct(
        public readonly int $status,
        public readonly bool $connected,
        public readonly ?string $connection,
        public readonly ?string $client,
        public readonly ?int $database,
        public readonly ?int $keyCount,
        public readonly ?int $responseTimeMs,
        public readonly ?float $instanceMemoryUsedMb,
        public readonly ?float $instanceMemoryMaxMb,
        public readonly ?string $message = null,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function key(): string
    {
        return 'redis';
    }

    public static function label(): string
    {
        return 'Redis';
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
            client: self::nullableStringValue($payload, 'client'),
            database: self::nullableIntValue($payload, 'database'),
            keyCount: self::nullableIntValue($payload, 'key_count'),
            responseTimeMs: self::nullableIntValue($payload, 'response_time_ms'),
            instanceMemoryUsedMb: self::nullableFloatValue($payload, 'instance_memory_used_mb'),
            instanceMemoryMaxMb: self::nullableFloatValue($payload, 'instance_memory_max_mb'),
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
            'client' => $this->client,
            'database' => $this->database,
            'key_count' => $this->keyCount,
            'response_time_ms' => $this->responseTimeMs,
            'instance_memory_used_mb' => $this->instanceMemoryUsedMb,
            'instance_memory_max_mb' => $this->instanceMemoryMaxMb,
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
