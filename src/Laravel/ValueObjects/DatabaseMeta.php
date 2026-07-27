<?php

namespace AppRadar\Agent\Laravel\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use AppRadar\Agent\Laravel\ValueObjects\Concerns\SerializesValueObject;

class DatabaseMeta implements Arrayable, JsonSerializable
{
    use SerializesValueObject;

    public function __construct(
        private readonly bool $connected,
        private readonly ?string $connection,
        private readonly ?string $type,
        private readonly ?string $database,
        private readonly ?float $sizeMb,
        private readonly ?int $responseTimeMs,
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
            'type' => $this->type,
            'database' => $this->database,
            'size_mb' => $this->sizeMb,
            'response_time_ms' => $this->responseTimeMs,
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
