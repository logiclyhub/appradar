<?php

namespace AppRadar\Agent\Laravel\Checks;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\QueueStatus;
use AppRadar\Agent\Laravel\Support\QueueMetricsStore;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\RedisQueue;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Symfony\Component\Process\Process;
use Throwable;

class QueueHealthCheck implements StatusCheckInterface
{
    private const STALE_WAITING_THRESHOLD_SECONDS = 900;

    private const STUCK_RUNNING_THRESHOLD_SECONDS = 3600;

    private const ACTIVITY_WINDOW_SECONDS = 900;

    public function __construct(
        private readonly QueueFactory $queue,
        private readonly QueueMetricsStore $metrics = new QueueMetricsStore(),
    ) {
    }

    public function run(): QueueStatus
    {
        $connectionName = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connectionName}.driver", $connectionName);
        $queueName = (string) config("queue.connections.{$connectionName}.queue", 'default');

        try {
            $connection = $this->queue->connection($connectionName);
            $pendingJobs = method_exists($connection, 'pendingSize')
                ? (int) $connection->pendingSize($queueName)
                : (int) $connection->size($queueName);
            $runningJobs = method_exists($connection, 'reservedSize') ? (int) $connection->reservedSize($queueName) : 0;
            $activeWorkers = $this->activeWorkers($connectionName, $queueName);
            $staleWaitingJobs = $this->staleWaitingJobs($connection, $queueName);
            $stuckJobs = $this->stuckRunningJobs($connection, $connectionName, $queueName);
            $activity = $this->metrics->snapshot(
                connection: $connectionName,
                queue: $queueName,
                recentWindowSeconds: self::ACTIVITY_WINDOW_SECONDS,
                defaultProcessedRecently: $pendingJobs === 0 && $runningJobs === 0,
            );
            $workerRunning = $activeWorkers > 0 || $activity['processed_recently'];

            return new QueueStatus(
                status: $this->status(
                    driver: $driver,
                    workerRunning: $workerRunning,
                    pendingJobs: $pendingJobs,
                    staleWaitingJobs: $staleWaitingJobs,
                    stuckJobs: $stuckJobs,
                    processedRecently: $activity['processed_recently'],
                    failingJobsRecently: $activity['failing_jobs_recently'],
                ),
                connected: true,
                connection: $connectionName,
                driver: $driver,
                queue: $queueName,
                activeWorkers: $activeWorkers,
                workerRunning: $workerRunning,
                pendingJobs: $pendingJobs,
                runningJobs: $runningJobs,
                staleWaitingJobsOver15Minutes: $staleWaitingJobs,
                stuckJobsOver1Hour: $stuckJobs,
                processedRecently: $activity['processed_recently'],
                failingJobsRecently: $activity['failing_jobs_recently'],
            );
        } catch (Throwable $throwable) {
            return new QueueStatus(
                status: StatusCodes::ERROR,
                connected: false,
                connection: $connectionName,
                driver: $driver,
                queue: $queueName,
                activeWorkers: 0,
                workerRunning: false,
                pendingJobs: 0,
                runningJobs: 0,
                staleWaitingJobsOver15Minutes: 0,
                stuckJobsOver1Hour: 0,
                processedRecently: false,
                failingJobsRecently: false,
                message: $throwable->getMessage(),
            );
        }
    }

    private function activeWorkers(string $connectionName, string $queueName): int
    {
        return $this->horizonWorkers($connectionName, $queueName) ?: $this->processWorkers($connectionName, $queueName);
    }

    private function horizonWorkers(string $connectionName, string $queueName): int
    {
        if (!class_exists(SupervisorRepository::class) || !app()->bound(SupervisorRepository::class)) {
            return 0;
        }

        $queueKey = $connectionName.':'.$queueName;

        try {
            $supervisors = app(SupervisorRepository::class)->all();

            return (int) collect($supervisors)->reduce(function (int $carry, object $supervisor) use ($queueKey) {
                $processes = is_array($supervisor->processes ?? null) ? $supervisor->processes : [];

                return $carry + (int) ($processes[$queueKey] ?? 0);
            }, 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function processWorkers(string $connectionName, string $queueName): int
    {
        foreach ([['ps', '-eo', 'command='], ['ps', '-axo', 'command=']] as $command) {
            try {
                $process = new Process($command);
                $process->setTimeout(5);
                $process->run();

                if (!$process->isSuccessful()) {
                    continue;
                }

                $count = $this->countMatchingWorkerProcesses($process->getOutput(), $connectionName, $queueName);

                if ($count > 0) {
                    return $count;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return 0;
    }

    private function countMatchingWorkerProcesses(string $output, string $connectionName, string $queueName): int
    {
        $artisanPath = base_path('artisan');
        $basePath = base_path();
        $commands = preg_split('/\r\n|\r|\n/', $output) ?: [];

        return (int) collect($commands)
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')
            ->filter(function (string $line) use ($artisanPath, $basePath): bool {
                if (!str_contains($line, 'artisan')) {
                    return false;
                }

                if (
                    !str_contains($line, 'queue:work')
                    && !str_contains($line, 'queue:listen')
                    && !str_contains($line, 'horizon')
                ) {
                    return false;
                }

                return str_contains($line, $artisanPath)
                    || str_contains($line, $basePath)
                    || preg_match('/(^| )php artisan (queue:work|queue:listen|horizon)( |$)/', $line) === 1
                    || preg_match('/(^| )artisan (queue:work|queue:listen|horizon)( |$)/', $line) === 1;
            })
            ->filter(fn (string $line) => $this->matchesQueueProcess($line, $connectionName, $queueName))
            ->count();
    }

    private function matchesQueueProcess(string $line, string $connectionName, string $queueName): bool
    {
        $queueMatches = [
            '--queue='.$queueName,
            '--queue '.$queueName,
            '--queue='.$connectionName,
            '--queue '.$connectionName,
        ];

        $connectionMatches = [
            ' queue:work '.$connectionName,
            ' queue:listen '.$connectionName,
        ];

        if (collect($queueMatches)->contains(fn (string $needle) => str_contains($line, $needle))) {
            return true;
        }

        if (collect($connectionMatches)->contains(fn (string $needle) => str_contains($line, $needle))) {
            return true;
        }

        return !str_contains($line, '--queue');
    }

    private function status(string $driver, bool $workerRunning, int $pendingJobs, int $staleWaitingJobs, int $stuckJobs, bool $processedRecently, bool $failingJobsRecently): int
    {
        if ($driver === 'sync') {
            return StatusCodes::WARN;
        }

        if (!$workerRunning) {
            return StatusCodes::ERROR;
        }

        if ($stuckJobs > 0) {
            return StatusCodes::ERROR;
        }

        if ($pendingJobs > 0 && !$processedRecently) {
            return StatusCodes::ERROR;
        }

        if ($failingJobsRecently) {
            return StatusCodes::ERROR;
        }

        if ($staleWaitingJobs > 0) {
            return StatusCodes::WARN;
        }

        return StatusCodes::OK;
    }

    private function staleWaitingJobs(object $connection, string $queueName): int
    {
        if (!$connection instanceof RedisQueue) {
            return 0;
        }

        $redis = $connection->getConnection();
        $queueKey = $connection->getQueue($queueName);
        $threshold = now()->timestamp - self::STALE_WAITING_THRESHOLD_SECONDS;
        $offset = 0;
        $chunkSize = 500;
        $count = 0;

        while (true) {
            $jobs = $redis->lrange($queueKey, $offset, $offset + $chunkSize - 1);

            if (!is_array($jobs) || $jobs === []) {
                break;
            }

            foreach ($jobs as $payload) {
                if (!is_string($payload)) {
                    continue;
                }

                $decoded = json_decode($payload, true);

                if (!is_array($decoded) || !isset($decoded['createdAt']) || !is_numeric($decoded['createdAt'])) {
                    continue;
                }

                if ((int) $decoded['createdAt'] <= $threshold) {
                    $count++;
                }
            }

            if (count($jobs) < $chunkSize) {
                break;
            }

            $offset += $chunkSize;
        }

        return $count;
    }

    private function stuckRunningJobs(object $connection, string $connectionName, string $queueName): int
    {
        if (!$connection instanceof RedisQueue) {
            return 0;
        }

        $retryAfter = (int) config("queue.connections.{$connectionName}.retry_after", 90);
        $reservedKey = $connection->getQueue($queueName).':reserved';
        $cutoffScore = now()->timestamp - self::STUCK_RUNNING_THRESHOLD_SECONDS + $retryAfter;

        return (int) $connection->getConnection()->zcount($reservedKey, '-inf', (string) $cutoffScore);
    }
}
