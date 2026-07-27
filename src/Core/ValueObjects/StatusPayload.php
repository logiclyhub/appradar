<?php

namespace AppRadar\Agent\Core\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class StatusPayload implements Arrayable, JsonSerializable
{
    /**
     * @param  array<int, CheckResult>  $checks
     */
    public function __construct(
        private readonly string $name,
        private readonly string $environment,
        private readonly int $status,
        private readonly string $checkedAt,
        private readonly array $checks,
    ) {
    }

    /**
     * @return array{name:string,environment:string,status:int,checked_at:string,checks:array<int,array<string,mixed>>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'environment' => $this->environment,
            'status' => $this->status,
            'checked_at' => $this->checkedAt,
            'checks' => array_map(
                static fn (CheckResult $check): array => $check->toArray(),
                $this->checks,
            ),
        ];
    }

    /**
     * @return array{name:string,environment:string,status:int,checked_at:string,checks:array<int,array<string,mixed>>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
