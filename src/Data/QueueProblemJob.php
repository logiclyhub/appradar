<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Data\Concerns\InteractsWithPayload;
use Carbon\CarbonImmutable;
use JsonSerializable;

class QueueProblemJob implements JsonSerializable
{
    use InteractsWithPayload;

    public function __construct(
        public readonly string $name,
        public readonly int $occurrences,
        public readonly int $failedOccurrences,
        public readonly int $timeoutOccurrences,
        public readonly int $maxAttemptsSeen,
        public readonly ?CarbonImmutable $firstSeenAt,
        public readonly ?CarbonImmutable $lastSeenAt,
        public readonly ?string $latestExceptionClass,
        public readonly ?string $latestExceptionMessage,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: self::nullableStringValue($payload, 'name') ?? 'Unknown Job',
            occurrences: self::intValue($payload, 'occurrences', 0),
            failedOccurrences: self::intValue($payload, 'failed_occurrences', 0),
            timeoutOccurrences: self::intValue($payload, 'timeout_occurrences', 0),
            maxAttemptsSeen: self::intValue($payload, 'max_attempts_seen', 0),
            firstSeenAt: self::timestampValue($payload, 'first_seen_at'),
            lastSeenAt: self::timestampValue($payload, 'last_seen_at'),
            latestExceptionClass: self::nullableStringValue($payload, 'latest_exception_class'),
            latestExceptionMessage: self::nullableStringValue($payload, 'latest_exception_message'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->withoutNullValues([
            'name' => $this->name,
            'occurrences' => $this->occurrences,
            'failed_occurrences' => $this->failedOccurrences,
            'timeout_occurrences' => $this->timeoutOccurrences,
            'max_attempts_seen' => $this->maxAttemptsSeen,
            'first_seen_at' => self::timestampString($this->firstSeenAt),
            'last_seen_at' => self::timestampString($this->lastSeenAt),
            'latest_exception_class' => $this->latestExceptionClass,
            'latest_exception_message' => $this->latestExceptionMessage,
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
