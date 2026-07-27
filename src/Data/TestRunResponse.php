<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Data\Concerns\InteractsWithPayload;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class TestRunResponse implements Arrayable, JsonSerializable
{
    use InteractsWithPayload;

    public function __construct(
        public readonly int $status,
        public readonly TestsStatus $tests,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            status: self::intValue($payload, 'status', 1),
            tests: TestsStatus::fromArray(is_array($payload['tests'] ?? null) ? $payload['tests'] : []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'tests' => $this->tests->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
