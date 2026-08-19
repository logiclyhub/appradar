<?php

namespace AppRadar\Agent\Data;

final class MaintenanceRuntime
{
    public function __construct(public readonly ?string $php = null, public readonly ?string $laravel = null) {}

    public function toArray(): array
    {
        return array_filter(['php' => $this->php, 'laravel' => $this->laravel], static fn (mixed $v): bool => $v !== null);
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            is_string($payload['php'] ?? null) ? $payload['php'] : null,
            is_string($payload['laravel'] ?? null) ? $payload['laravel'] : null,
        );
    }
}
