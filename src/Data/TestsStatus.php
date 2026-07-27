<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Core\Contracts\StatusSectionInterface;
use AppRadar\Agent\Data\Concerns\InteractsWithPayload;
use Carbon\CarbonImmutable;

class TestsStatus implements StatusSectionInterface
{
    use InteractsWithPayload;

    public function __construct(
        public readonly int $status,
        public readonly bool $hasRun,
        public readonly ?CarbonImmutable $lastRunAt,
        public readonly ?bool $success,
        public readonly ?int $exitCode,
        public readonly ?float $durationSeconds,
        public readonly ?int $tests,
        public readonly ?int $assertions,
        public readonly ?int $failures,
        public readonly ?int $errors,
        public readonly ?int $skipped,
        public readonly bool $coverageAvailable,
        public readonly ?float $coveragePercent,
        public readonly ?string $message = null,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function key(): string
    {
        return 'tests';
    }

    public static function label(): string
    {
        return 'Tests';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): static
    {
        return new self(
            status: self::intValue($payload, 'status', 1),
            hasRun: self::boolValue($payload, 'has_run'),
            lastRunAt: self::timestampValue($payload, 'last_run_at'),
            success: self::nullableBoolValue($payload, 'success'),
            exitCode: self::nullableIntValue($payload, 'exit_code'),
            durationSeconds: self::nullableFloatValue($payload, 'duration_seconds'),
            tests: self::nullableIntValue($payload, 'tests'),
            assertions: self::nullableIntValue($payload, 'assertions'),
            failures: self::nullableIntValue($payload, 'failures'),
            errors: self::nullableIntValue($payload, 'errors'),
            skipped: self::nullableIntValue($payload, 'skipped'),
            coverageAvailable: self::boolValue($payload, 'coverage_available'),
            coveragePercent: self::nullableFloatValue($payload, 'coverage_percent'),
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
            'has_run' => $this->hasRun,
            'last_run_at' => self::timestampString($this->lastRunAt),
            'success' => $this->success,
            'exit_code' => $this->exitCode,
            'duration_seconds' => $this->durationSeconds,
            'tests' => $this->tests,
            'assertions' => $this->assertions,
            'failures' => $this->failures,
            'errors' => $this->errors,
            'skipped' => $this->skipped,
            'coverage_available' => $this->coverageAvailable,
            'coverage_percent' => $this->coveragePercent,
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
