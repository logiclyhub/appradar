<?php

namespace AppRadar\Agent\Laravel\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use AppRadar\Agent\Laravel\ValueObjects\Concerns\SerializesValueObject;

class RedisMeta implements Arrayable, JsonSerializable
{
    use SerializesValueObject;

    public function __construct(
        private readonly bool $connected,
        private readonly ?string $connection,
        private readonly ?string $client,
        private readonly ?int $database,
        private readonly ?int $keyCount,
        private readonly ?int $responseTimeMs,
        private readonly ?float $instanceMemoryUsedMb,
        private readonly ?float $instanceMemoryMaxMb,
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
