<?php

namespace AppRadar\Agent\Laravel\Checks;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use AppRadar\Agent\Core\CheckType;
use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Core\ValueObjects\CheckResult;
use AppRadar\Agent\Laravel\Support\SchedulerMetricsStore;
use AppRadar\Agent\Laravel\Support\SchedulerTaskDescriptor;
use AppRadar\Agent\Laravel\Support\StatusFileStore;
use AppRadar\Agent\Laravel\ValueObjects\SchedulerMeta;
use Throwable;

class SchedulerHeartbeatCheck implements StatusCheckInterface
{
    private const EXPECTED_INTERVAL_SECONDS = 60;

    public function __construct(
        private readonly Schedule $schedule,
        private readonly StatusFileStore $store = new StatusFileStore(),
        private readonly SchedulerMetricsStore $metrics = new SchedulerMetricsStore(),
    ) {
    }

    public function run(): CheckResult
    {
        $metrics = $this->metrics->snapshot();
        $registeredCrons = $this->registeredCronCount();

        try {
            $payload = $this->store->readJson('scheduler-heartbeat.json') ?? [];
            $lastHeartbeatAt = $payload['checked_at'] ?? null;

            if (!is_string($lastHeartbeatAt) || $lastHeartbeatAt === '') {
                return new CheckResult(
                    type: CheckType::Scheduler,
                    status: StatusCodes::ERROR,
                    meta: new SchedulerMeta(
                        running: false,
                        lastHeartbeatAt: null,
                        lastSuccessfulRunSecondsAgo: null,
                        expectedIntervalSeconds: self::EXPECTED_INTERVAL_SECONDS,
                        registeredCrons: $registeredCrons,
                        failedCronsRecently: $metrics['failed_crons_recently'],
                        successfulCronsRecently: $metrics['successful_crons_recently'],
                        runningCrons: $metrics['running_crons'],
                        slowCrons: $metrics['slow_crons'],
                        message: 'Heartbeat file not found or invalid.',
                    ),
                );
            }

            $ageSeconds = now()->diffInSeconds(Carbon::parse($lastHeartbeatAt));
            $running = $ageSeconds <= 300;
            $status = $this->status($running, $ageSeconds, $metrics);

            return new CheckResult(
                type: CheckType::Scheduler,
                status: $status,
                meta: new SchedulerMeta(
                    running: $running,
                    lastHeartbeatAt: $lastHeartbeatAt,
                    lastSuccessfulRunSecondsAgo: $ageSeconds,
                    expectedIntervalSeconds: self::EXPECTED_INTERVAL_SECONDS,
                    registeredCrons: $registeredCrons,
                    failedCronsRecently: $metrics['failed_crons_recently'],
                    successfulCronsRecently: $metrics['successful_crons_recently'],
                    runningCrons: $metrics['running_crons'],
                    slowCrons: $metrics['slow_crons'],
                ),
            );
        } catch (Throwable $throwable) {
            return new CheckResult(
                type: CheckType::Scheduler,
                status: StatusCodes::ERROR,
                meta: new SchedulerMeta(
                    running: false,
                    lastHeartbeatAt: null,
                    lastSuccessfulRunSecondsAgo: null,
                    expectedIntervalSeconds: self::EXPECTED_INTERVAL_SECONDS,
                    registeredCrons: $registeredCrons,
                    failedCronsRecently: $metrics['failed_crons_recently'],
                    successfulCronsRecently: $metrics['successful_crons_recently'],
                    runningCrons: $metrics['running_crons'],
                    slowCrons: $metrics['slow_crons'],
                    message: $throwable->getMessage(),
                ),
            );
        }
    }

    /**
     * @param  array{successful_crons_recently:int,failed_crons_recently:int,running_crons:int,slow_crons:int}  $metrics
     */
    private function status(bool $running, int $ageSeconds, array $metrics): int
    {
        if (!$running) {
            return StatusCodes::ERROR;
        }

        if ($metrics['failed_crons_recently'] > 0) {
            return StatusCodes::WARN;
        }

        if ($metrics['slow_crons'] > 0) {
            return StatusCodes::WARN;
        }

        if ($ageSeconds > 120) {
            return StatusCodes::WARN;
        }

        return StatusCodes::OK;
    }

    private function registeredCronCount(): int
    {
        return (int) collect($this->schedule->events())
            ->map(static fn ($event) => SchedulerTaskDescriptor::fromEvent($event))
            ->reject(static fn (SchedulerTaskDescriptor $task) => $task->isInternalHeartbeat())
            ->count();
    }
}
