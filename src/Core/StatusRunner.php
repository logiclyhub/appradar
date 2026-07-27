<?php

namespace AppRadar\Agent\Core;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\ValueObjects\CheckResult;
use AppRadar\Agent\Core\ValueObjects\StatusRunResult;

class StatusRunner
{
    /**
     * @param  array<int, StatusCheckInterface>  $checks
     */
    public function run(array $checks): StatusRunResult
    {
        $results = array_map(static fn (StatusCheckInterface $check) => $check->run(), $checks);

        return new StatusRunResult($this->overallStatus($results), $results);
    }

    /**
     * @param  array<int, CheckResult>  $checks
     */
    public function overallStatus(array $checks): int
    {
        if (collect($checks)->contains(fn (CheckResult $check) => $check->status() === StatusCodes::ERROR)) {
            return StatusCodes::ERROR;
        }

        if (collect($checks)->contains(fn (CheckResult $check) => $check->status() === StatusCodes::WARN)) {
            return StatusCodes::WARN;
        }

        return StatusCodes::OK;
    }
}
