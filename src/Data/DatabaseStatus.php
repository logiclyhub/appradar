<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Core\Contracts\StatusSectionInterface;
use AppRadar\Agent\Data\Concerns\InteractsWithPayload;

class DatabaseStatus implements StatusSectionInterface
{
    use InteractsWithPayload;

    public function __construct(
        public readonly int $status,
        public readonly bool $connected,
        public readonly ?string $connection,
        public readonly ?string $type,
        public readonly ?string $database,
        public readonly ?float $sizeMb,
        public readonly ?int $responseTimeMs,
        public readonly ?string $message = null,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function key(): string
    {
        return 'database';
    }

    public static function label(): string
    {
        return 'Database';
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
            type: self::nullableStringValue($payload, 'type'),
            database: self::nullableStringValue($payload, 'database'),
            sizeMb: self::nullableFloatValue($payload, 'size_mb'),
            responseTimeMs: self::nullableIntValue($payload, 'response_time_ms'),
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
