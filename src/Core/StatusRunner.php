<?php

namespace AppRadar\Agent\Core;

use AppRadar\Agent\Core\Contracts\StatusSectionInterface;

class StatusRunner
{
    /**
     * @param  array<int, StatusSectionInterface>  $checks
     */
    public function overallStatus(array $checks): int
    {
        if (collect($checks)->contains(fn (StatusSectionInterface $check) => $check->status() === StatusCodes::ERROR)) {
            return StatusCodes::ERROR;
        }

        if (collect($checks)->contains(fn (StatusSectionInterface $check) => $check->status() === StatusCodes::WARN)) {
            return StatusCodes::WARN;
        }

        return StatusCodes::OK;
    }
}
