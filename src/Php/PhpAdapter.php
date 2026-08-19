<?php

namespace AppRadar\Agent\Php;

use AppRadar\Agent\Core\Contracts\AdapterInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Core\StatusRunner;
use AppRadar\Agent\Data\StatusReport;
use AppRadar\Agent\Data\TestRunResponse;
use AppRadar\Agent\Data\TestsStatus;
use AppRadar\Agent\Php\Checks\MaintenanceCheck;
use AppRadar\Agent\Php\Checks\DatabaseCheck;
use AppRadar\Agent\Php\Checks\RedisCheck;
use AppRadar\Agent\Php\Checks\SecurityCheck;
use AppRadar\Agent\Php\Checks\UnsupportedQueueCheck;
use AppRadar\Agent\Php\Checks\UnsupportedSchedulerCheck;
use AppRadar\Agent\Php\Checks\UnsupportedTestsCheck;
use AppRadar\Agent\Php\Config\PhpAgentConfig;
use Carbon\CarbonImmutable;

class PhpAdapter implements AdapterInterface
{
    public function __construct(
        private readonly PhpAgentConfig $config,
        private readonly StatusRunner $statusRunner = new StatusRunner(),
    ) {
    }

    public function statusPayload(): array
    {
        $database = (new DatabaseCheck($this->config->database))->run();
        $redis = (new RedisCheck($this->config->redis))->run();
        $scheduler = (new UnsupportedSchedulerCheck())->run();
        $queue = (new UnsupportedQueueCheck())->run();
        $tests = (new UnsupportedTestsCheck())->run();
        $security = (new SecurityCheck($this->config))->run();
        $maintenance = (new MaintenanceCheck($this->config))->run();

        return (new StatusReport(
            name: $this->config->name,
            environment: $this->config->environment,
            status: $this->statusRunner->overallStatus([$database, $redis, $scheduler, $queue, $tests, $security, $maintenance]),
            checkedAt: CarbonImmutable::now(),
            database: $database,
            redis: $redis,
            scheduler: $scheduler,
            queue: $queue,
            tests: $tests,
            security: $security,
            maintenance: $maintenance,
        ))->toArray();
    }

    public function runTests(int $timeout = 600): array
    {
        $tests = (new UnsupportedTestsCheck())->run();

        return (new TestRunResponse(
            status: StatusCodes::WARN,
            tests: $tests,
        ))->toArray();
    }
}
