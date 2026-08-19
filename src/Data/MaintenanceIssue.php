<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Data\Concerns\InteractsWithPayload;

final class MaintenanceIssue
{
    use InteractsWithPayload;

    public function __construct(
        public readonly string $id,
        public readonly int $severity,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $remediation = null,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            id: self::nullableStringValue($payload, 'id') ?? 'unknown',
            severity: self::intValue($payload, 'severity', 1),
            title: self::nullableStringValue($payload, 'title') ?? 'Maintenance issue',
            message: self::nullableStringValue($payload, 'message') ?? '',
            remediation: self::nullableStringValue($payload, 'remediation'),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'severity' => $this->severity,
            'title' => $this->title,
            'message' => $this->message,
            'remediation' => $this->remediation,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
