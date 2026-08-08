<?php

namespace AppRadar\Agent\Php\Checks;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SchedulerStatus;

class UnsupportedSchedulerCheck implements StatusCheckInterface
{
    public function run(): SchedulerStatus
    {
        return new SchedulerStatus(
            status: StatusCodes::WARN,
            running: false,
            lastHeartbeatAt: null,
            lastSuccessfulRunSecondsAgo: null,
            expectedIntervalSeconds: 60,
            registeredCrons: 0,
            failedCronsRecently: 0,
            successfulCronsRecently: 0,
            runningCrons: 0,
            slowCrons: 0,
            message: 'Scheduler health is not available for plain PHP without framework integrations',
        );
    }
}
