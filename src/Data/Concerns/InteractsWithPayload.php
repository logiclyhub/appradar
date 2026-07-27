<?php

namespace AppRadar\Agent\Data\Concerns;

use Carbon\CarbonImmutable;

trait InteractsWithPayload
{
    protected static function intValue(array $payload, string $key, int $default = 0): int
    {
        return isset($payload[$key]) && is_numeric($payload[$key]) ? (int) $payload[$key] : $default;
    }

    protected static function boolValue(array $payload, string $key, bool $default = false): bool
    {
        return isset($payload[$key]) ? (bool) $payload[$key] : $default;
    }

    protected static function nullableIntValue(array $payload, string $key): ?int
    {
        return isset($payload[$key]) && is_numeric($payload[$key]) ? (int) $payload[$key] : null;
    }

    protected static function nullableFloatValue(array $payload, string $key): ?float
    {
        return isset($payload[$key]) && is_numeric($payload[$key]) ? (float) $payload[$key] : null;
    }

    protected static function nullableStringValue(array $payload, string $key): ?string
    {
        return isset($payload[$key]) && is_string($payload[$key]) ? $payload[$key] : null;
    }

    protected static function nullableBoolValue(array $payload, string $key): ?bool
    {
        return array_key_exists($key, $payload) ? (bool) $payload[$key] : null;
    }

    protected static function timestampValue(array $payload, string $key): ?CarbonImmutable
    {
        $value = self::nullableStringValue($payload, $key);

        return $value !== null ? CarbonImmutable::parse($value) : null;
    }

    protected static function timestampString(?CarbonImmutable $value): ?string
    {
        return $value?->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withoutNullValues(array $payload): array
    {
        return array_filter($payload, static fn (mixed $value): bool => $value !== null);
    }
}
