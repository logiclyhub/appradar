<?php

namespace AppRadar\Agent\Php\Checks;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\QueueStatus;

class UnsupportedQueueCheck implements StatusCheckInterface
{
    public function run(): QueueStatus
    {
        return new QueueStatus(
            status: StatusCodes::WARN,
            connected: false,
            connection: null,
            driver: null,
            queue: null,
            retryAfterSeconds: null,
            blockForSeconds: null,
            afterCommit: null,
            activeWorkers: 0,
            workerRunning: false,
            pendingJobs: 0,
            runningJobs: 0,
            staleWaitingJobsOver15Minutes: 0,
            stuckJobsOver1Hour: 0,
            processedRecently: false,
            failingJobsRecently: false,
            completedJobsRecentlyCount: 0,
            failedJobsRecentlyCount: 0,
            exceptionOccurrencesRecently: 0,
            timeoutOccurrencesRecently: 0,
            problemJobsCount: 0,
            problemJobs: [],
            workerTimeoutSeconds: null,
            workerSleepSeconds: null,
            workerTries: null,
            workerMemoryMb: null,
            workerBackoffSeconds: null,
            workerMaxTimeSeconds: null,
            workerCommand: null,
            message: 'Queue health is not available for plain PHP without framework integrations',
        );
    }
}
