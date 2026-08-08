<?php

namespace AppRadar\Agent\Php\Checks;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\TestsStatus;

class UnsupportedTestsCheck implements StatusCheckInterface
{
    public function run(): TestsStatus
    {
        return new TestsStatus(
            status: StatusCodes::WARN,
            hasRun: false,
            lastRunAt: null,
            success: null,
            exitCode: null,
            durationSeconds: null,
            tests: null,
            assertions: null,
            failures: null,
            errors: null,
            skipped: null,
            coverageAvailable: false,
            coveragePercent: null,
            message: 'Test runs are not available for plain PHP without framework integrations',
        );
    }
}
