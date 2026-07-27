<?php

namespace AppRadar\Agent\Laravel\ValueObjects\Concerns;

trait SerializesValueObject
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withoutNullValues(array $payload): array
    {
        return array_filter($payload, static fn (mixed $value): bool => $value !== null);
    }
}
