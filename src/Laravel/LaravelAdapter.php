<?php

namespace AppRadar\Agent\Laravel;

use AppRadar\Agent\Core\Contracts\AdapterInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Core\StatusRunner;
use AppRadar\Agent\Core\ValueObjects\StatusPayload;
use AppRadar\Agent\Core\ValueObjects\TestRunResponse;
use AppRadar\Agent\Laravel\Checks\DatabaseCheck;
use AppRadar\Agent\Laravel\Checks\QueueHealthCheck;
use AppRadar\Agent\Laravel\Checks\RedisCheck;
use AppRadar\Agent\Laravel\Checks\SchedulerHeartbeatCheck;
use AppRadar\Agent\Laravel\Checks\TestsCheck;
use AppRadar\Agent\Laravel\Support\TestRunner;
use AppRadar\Agent\Laravel\ValueObjects\TestsMeta;

class LaravelAdapter implements AdapterInterface
{
    public function statusPayload(): array
    {
        $result = app(StatusRunner::class)->run([
            app(DatabaseCheck::class),
            app(RedisCheck::class),
            app(SchedulerHeartbeatCheck::class),
            app(QueueHealthCheck::class),
            app(TestsCheck::class),
        ]);

        return (new StatusPayload(
            name: (string) config('app.name'),
            environment: app()->environment(),
            status: $result->status(),
            checkedAt: now()->toIso8601String(),
            checks: $result->checks(),
        ))->toArray();
    }

    public function runTests(int $timeout = 600): array
    {
        $runner = app(TestRunner::class);
        $result = $runner->run($timeout);
        $meta = new TestsMeta(
            hasRun: true,
            lastRunAt: isset($result['last_run_at']) ? (string) $result['last_run_at'] : null,
            success: isset($result['success']) ? (bool) $result['success'] : null,
            exitCode: isset($result['exit_code']) ? (int) $result['exit_code'] : null,
            durationSeconds: isset($result['duration_seconds']) ? (float) $result['duration_seconds'] : null,
            tests: isset($result['tests']) ? (int) $result['tests'] : null,
            assertions: isset($result['assertions']) ? (int) $result['assertions'] : null,
            failures: isset($result['failures']) ? (int) $result['failures'] : null,
            errors: isset($result['errors']) ? (int) $result['errors'] : null,
            skipped: isset($result['skipped']) ? (int) $result['skipped'] : null,
            coverageAvailable: $runner->coverageDriver() !== null,
            coveragePercent: isset($result['coverage_percent']) ? (float) $result['coverage_percent'] : null,
        );

        return (new TestRunResponse(
            status: ($result['success'] ?? false) ? StatusCodes::OK : StatusCodes::ERROR,
            result: $meta,
        ))->toArray();
    }
}
