<?php

namespace AppRadar\Agent\Laravel\Checks;

use AppRadar\Agent\Core\CheckType;
use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Core\ValueObjects\CheckResult;
use AppRadar\Agent\Laravel\Support\StatusFileStore;
use AppRadar\Agent\Laravel\Support\TestRunner;
use AppRadar\Agent\Laravel\ValueObjects\TestsMeta;
use Throwable;

class TestsCheck implements StatusCheckInterface
{
    public function __construct(
        private readonly StatusFileStore $store = new StatusFileStore(),
        private readonly TestRunner $runner = new TestRunner(),
    ) {
    }

    public function run(): CheckResult
    {
        $coverageAvailable = $this->runner->coverageDriver() !== null;

        if (!$this->store->exists('test-run.json')) {
            return new CheckResult(
                type: CheckType::Tests,
                status: StatusCodes::WARN,
                meta: new TestsMeta(
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
                    coverageAvailable: $coverageAvailable,
                    coveragePercent: null,
                ),
            );
        }

        try {
            $payload = $this->store->readJson('test-run.json') ?? [];
            $meta = $this->metaFromPayload($payload, $coverageAvailable);

            return new CheckResult(
                type: CheckType::Tests,
                status: $this->statusFromPayload($payload),
                meta: $meta,
            );
        } catch (Throwable $throwable) {
            return new CheckResult(
                type: CheckType::Tests,
                status: StatusCodes::ERROR,
                meta: new TestsMeta(
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
                    coverageAvailable: $coverageAvailable,
                    coveragePercent: null,
                    message: $throwable->getMessage(),
                ),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function metaFromPayload(array $payload, bool $coverageAvailable): TestsMeta
    {
        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        $coverage = is_array($payload['coverage'] ?? null) ? $payload['coverage'] : [];

        return new TestsMeta(
            hasRun: true,
            lastRunAt: is_string($payload['last_run_at'] ?? null)
                ? $payload['last_run_at']
                : (is_string($payload['ran_at'] ?? null) ? $payload['ran_at'] : null),
            success: isset($payload['success']) ? (bool) $payload['success'] : null,
            exitCode: isset($payload['exit_code']) ? (int) $payload['exit_code'] : null,
            durationSeconds: isset($payload['duration_seconds']) ? (float) $payload['duration_seconds'] : null,
            tests: isset($payload['tests']) ? (int) $payload['tests'] : (isset($summary['tests']) ? (int) $summary['tests'] : null),
            assertions: isset($payload['assertions']) ? (int) $payload['assertions'] : (isset($summary['assertions']) ? (int) $summary['assertions'] : null),
            failures: isset($payload['failures']) ? (int) $payload['failures'] : (isset($summary['failures']) ? (int) $summary['failures'] : null),
            errors: isset($payload['errors']) ? (int) $payload['errors'] : (isset($summary['errors']) ? (int) $summary['errors'] : null),
            skipped: isset($payload['skipped']) ? (int) $payload['skipped'] : (isset($summary['skipped']) ? (int) $summary['skipped'] : null),
            coverageAvailable: $coverageAvailable,
            coveragePercent: isset($payload['coverage_percent'])
                ? (float) $payload['coverage_percent']
                : (isset($coverage['line_coverage_percent']) ? (float) $coverage['line_coverage_percent'] : null),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function statusFromPayload(array $payload): int
    {
        return ($payload['success'] ?? false) ? StatusCodes::OK : StatusCodes::ERROR;
    }
}
