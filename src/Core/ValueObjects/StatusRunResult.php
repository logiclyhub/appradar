<?php

namespace AppRadar\Agent\Core\ValueObjects;

class StatusRunResult
{
    /**
     * @param  array<int, CheckResult>  $checks
     */
    public function __construct(
        private readonly int $status,
        private readonly array $checks,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<int, CheckResult>
     */
    public function checks(): array
    {
        return $this->checks;
    }
}
