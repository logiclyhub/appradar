<?php

namespace AppRadar\Agent\Laravel;

use AppRadar\Agent\Core\Contracts\AdapterInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Core\StatusRunner;
use AppRadar\Agent\Data\StatusReport;
use AppRadar\Agent\Data\TestRunResponse;
use AppRadar\Agent\Data\TestsStatus;
use AppRadar\Agent\Laravel\Checks\DatabaseCheck;
use AppRadar\Agent\Laravel\Checks\QueueHealthCheck;
use AppRadar\Agent\Laravel\Checks\RedisCheck;
use AppRadar\Agent\Laravel\Checks\SchedulerHeartbeatCheck;
use AppRadar\Agent\Laravel\Checks\SecurityCheck;
use AppRadar\Agent\Laravel\Checks\TestsCheck;
use AppRadar\Agent\Laravel\Support\TestRunner;

class LaravelAdapter implements AdapterInterface
{
    public function statusPayload(): array
    {
        $database = app(DatabaseCheck::class)->run();
        $redis = app(RedisCheck::class)->run();
        $scheduler = app(SchedulerHeartbeatCheck::class)->run();
        $queue = app(QueueHealthCheck::class)->run();
        $tests = app(TestsCheck::class)->run();
        $security = app(SecurityCheck::class)->run();

        return (new StatusReport(
            name: (string) config('app.name'),
            environment: app()->environment(),
            status: app(StatusRunner::class)->overallStatus([$database, $redis, $scheduler, $queue, $tests, $security]),
            checkedAt: now()->toImmutable(),
            database: $database,
            redis: $redis,
            scheduler: $scheduler,
            queue: $queue,
            tests: $tests,
            security: $security,
        ))->toArray();
    }

    public function runTests(int $timeout = 600): array
    {
        $runner = app(TestRunner::class);
        $result = $runner->run($timeout);
        $tests = TestsStatus::fromArray([
            'status' => ($result['success'] ?? false) ? StatusCodes::OK : StatusCodes::ERROR,
            ...$result,
        ]);

        return (new TestRunResponse(
            status: ($result['success'] ?? false) ? StatusCodes::OK : StatusCodes::ERROR,
            tests: $tests,
        ))->toArray();
    }
}
