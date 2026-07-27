<?php

namespace AppRadar\Agent\Laravel\Checks;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\TestsStatus;
use AppRadar\Agent\Laravel\Support\StatusFileStore;
use AppRadar\Agent\Laravel\Support\TestRunner;
use Throwable;

class TestsCheck implements StatusCheckInterface
{
    public function __construct(
        private readonly StatusFileStore $store = new StatusFileStore(),
        private readonly TestRunner $runner = new TestRunner(),
    ) {
    }

    public function run(): TestsStatus
    {
        $coverageAvailable = $this->runner->coverageDriver() !== null;

        if (!$this->store->exists('test-run.json')) {
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
                coverageAvailable: $coverageAvailable,
                coveragePercent: null,
            );
        }

        try {
            $payload = $this->store->readJson('test-run.json') ?? [];
            $testsStatus = $this->testsStatusFromPayload($payload, $coverageAvailable);

            return $testsStatus;
        } catch (Throwable $throwable) {
            return new TestsStatus(
                status: StatusCodes::ERROR,
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
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function testsStatusFromPayload(array $payload, bool $coverageAvailable): TestsStatus
    {
        return TestsStatus::fromArray([
            'status' => $this->statusFromPayload($payload),
            'has_run' => true,
            'last_run_at' => $payload['last_run_at'] ?? $payload['ran_at'] ?? null,
            'success' => $payload['success'] ?? null,
            'exit_code' => $payload['exit_code'] ?? null,
            'duration_seconds' => $payload['duration_seconds'] ?? null,
            'tests' => $payload['tests'] ?? $payload['summary']['tests'] ?? null,
            'assertions' => $payload['assertions'] ?? $payload['summary']['assertions'] ?? null,
            'failures' => $payload['failures'] ?? $payload['summary']['failures'] ?? null,
            'errors' => $payload['errors'] ?? $payload['summary']['errors'] ?? null,
            'skipped' => $payload['skipped'] ?? $payload['summary']['skipped'] ?? null,
            'coverage_available' => $coverageAvailable,
            'coverage_percent' => $payload['coverage_percent'] ?? $payload['coverage']['line_coverage_percent'] ?? null,
            'message' => $payload['message'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function statusFromPayload(array $payload): int
    {
        return ($payload['success'] ?? false) ? StatusCodes::OK : StatusCodes::ERROR;
    }
}
