<?php

namespace AppRadar\Agent\Laravel\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use AppRadar\Agent\Laravel\ValueObjects\Concerns\SerializesValueObject;

class TestsMeta implements Arrayable, JsonSerializable
{
    use SerializesValueObject;

    public function __construct(
        private readonly bool $hasRun,
        private readonly ?string $lastRunAt,
        private readonly ?bool $success,
        private readonly ?int $exitCode,
        private readonly ?float $durationSeconds,
        private readonly ?int $tests,
        private readonly ?int $assertions,
        private readonly ?int $failures,
        private readonly ?int $errors,
        private readonly ?int $skipped,
        private readonly bool $coverageAvailable,
        private readonly ?float $coveragePercent,
        private readonly ?string $message = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->withoutNullValues([
            'has_run' => $this->hasRun,
            'last_run_at' => $this->lastRunAt,
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
