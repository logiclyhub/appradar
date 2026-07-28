<?php

namespace AppRadar\Agent\Laravel\Checks;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\QueueProblemJob;
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

    public function __construct(
        private readonly QueueFactory $queue,
        private readonly QueueMetricsStore $metrics = new QueueMetricsStore(),
    ) {
    }

    public function run(): QueueStatus
    {
        $connectionName = (string) config('queue.default');
        $connectionConfig = config("queue.connections.{$connectionName}", []);
        $driver = (string) data_get($connectionConfig, 'driver', $connectionName);
        $queueName = (string) data_get($connectionConfig, 'queue', 'default');
        $retryAfter = is_numeric(data_get($connectionConfig, 'retry_after')) ? (int) data_get($connectionConfig, 'retry_after') : null;
        $blockFor = is_numeric(data_get($connectionConfig, 'block_for')) ? (int) data_get($connectionConfig, 'block_for') : null;
        $afterCommit = array_key_exists('after_commit', $connectionConfig) ? (bool) data_get($connectionConfig, 'after_commit') : null;

        try {
            $connection = $this->queue->connection($connectionName);
            $pendingJobs = method_exists($connection, 'pendingSize')
                ? (int) $connection->pendingSize($queueName)
                : (int) $connection->size($queueName);
            $runningJobs = method_exists($connection, 'reservedSize') ? (int) $connection->reservedSize($queueName) : 0;
            $workerInspection = $this->inspectWorkers($connectionName, $queueName);
            $activeWorkers = $workerInspection['count'];
            $staleWaitingJobs = $this->staleWaitingJobs($connection, $queueName);
            $stuckJobs = $this->stuckRunningJobs($connection, $connectionName, $queueName);
            $activity = $this->metrics->snapshot(
                connection: $connectionName,
                queue: $queueName,
                activityWindowSeconds: (int) config('appradar.queue.activity_window_seconds', 900),
                problemWindowSeconds: (int) config('appradar.queue.problem_window_seconds', 3600),
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
                    timeoutOccurrencesRecently: $activity['timeout_occurrences_recently'],
                    problemJobsCount: $activity['problem_jobs_count'],
                ),
                connected: true,
                connection: $connectionName,
                driver: $driver,
                queue: $queueName,
                retryAfterSeconds: $retryAfter,
                blockForSeconds: $blockFor,
                afterCommit: $afterCommit,
                activeWorkers: $activeWorkers,
                workerRunning: $workerRunning,
                pendingJobs: $pendingJobs,
                runningJobs: $runningJobs,
                staleWaitingJobsOver15Minutes: $staleWaitingJobs,
                stuckJobsOver1Hour: $stuckJobs,
                processedRecently: $activity['processed_recently'],
                failingJobsRecently: $activity['failing_jobs_recently'],
                exceptionOccurrencesRecently: $activity['exception_occurrences_recently'],
                timeoutOccurrencesRecently: $activity['timeout_occurrences_recently'],
                problemJobsCount: $activity['problem_jobs_count'],
                problemJobs: collect($activity['problem_jobs'] ?? [])
                    ->filter(fn (mixed $job): bool => is_array($job))
                    ->map(fn (array $job): QueueProblemJob => QueueProblemJob::fromArray($job))
                    ->values()
                    ->all(),
                workerTimeoutSeconds: $workerInspection['options']['timeout'] ?? null,
                workerSleepSeconds: $workerInspection['options']['sleep'] ?? null,
                workerTries: $workerInspection['options']['tries'] ?? null,
                workerMemoryMb: $workerInspection['options']['memory'] ?? null,
                workerBackoffSeconds: $workerInspection['options']['backoff'] ?? null,
                workerMaxTimeSeconds: $workerInspection['options']['max_time'] ?? null,
                workerCommand: null,
            );
        } catch (Throwable $throwable) {
            return new QueueStatus(
                status: StatusCodes::ERROR,
                connected: false,
                connection: $connectionName,
                driver: $driver,
                queue: $queueName,
                retryAfterSeconds: $retryAfter,
                blockForSeconds: $blockFor,
                afterCommit: $afterCommit,
                activeWorkers: 0,
                workerRunning: false,
                pendingJobs: 0,
                runningJobs: 0,
                staleWaitingJobsOver15Minutes: 0,
                stuckJobsOver1Hour: 0,
                processedRecently: false,
                failingJobsRecently: false,
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
                message: $throwable->getMessage(),
            );
        }
    }

    /**
     * @return array{count:int, options:array<string,int>, command:?string}
     */
    private function inspectWorkers(string $connectionName, string $queueName): array
    {
        $processWorkers = $this->processWorkers($connectionName, $queueName);

        if ($processWorkers['count'] > 0) {
            return $processWorkers;
        }

        return [
            'count' => $this->horizonWorkers($connectionName, $queueName),
            'options' => [],
            'command' => null,
        ];
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

    /**
     * @return array{count:int, options:array<string,int>, command:?string}
     */
    private function processWorkers(string $connectionName, string $queueName): array
    {
        foreach ([['ps', '-eo', 'pid=,command='], ['ps', '-axo', 'pid=,command=']] as $command) {
            try {
                $process = new Process($command);
                $process->setTimeout(5);
                $process->run();

                if (!$process->isSuccessful()) {
                    continue;
                }

                $matches = $this->matchingWorkerProcesses($process->getOutput(), $connectionName, $queueName);
                $count = count($matches);

                if ($count > 0) {
                    $sample = $this->workerCommandSample($matches);

                    return [
                        'count' => $count,
                        'options' => $sample ? $this->parseWorkerOptions($sample['command']) : [],
                        'command' => $sample['command'] ?? null,
                    ];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return [
            'count' => 0,
            'options' => [],
            'command' => null,
        ];
    }

    /**
     * @return array<int, array{pid:int,command:string}>
     */
    private function matchingWorkerProcesses(string $output, string $connectionName, string $queueName): array
    {
        $artisanPath = base_path('artisan');
        $basePath = base_path();
        $commands = preg_split('/\r\n|\r|\n/', $output) ?: [];

        return collect($commands)
            ->map(fn (string $line) => $this->parseProcessLine($line))
            ->filter(fn (?array $process) => $process !== null)
            ->filter(function (array $process) use ($artisanPath, $basePath): bool {
                $line = $process['command'];

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

                return $this->matchesBasePath($process['pid'], $basePath)
                    || str_contains($line, $artisanPath)
                    || str_contains($line, $basePath)
                    || preg_match('/(^| )php artisan (queue:work|queue:listen|horizon)( |$)/', $line) === 1 && $this->matchesBasePath($process['pid'], $basePath)
                    || preg_match('/(^| )artisan (queue:work|queue:listen|horizon)( |$)/', $line) === 1 && $this->matchesBasePath($process['pid'], $basePath);
            })
            ->filter(fn (array $process) => $this->matchesQueueProcess($process['command'], $connectionName, $queueName))
            ->values()
            ->all();
    }

    /**
     * @return array{pid:int,command:string}|null
     */
    private function parseProcessLine(string $line): ?array
    {
        $line = trim($line);

        if ($line === '' || !preg_match('/^(\d+)\s+(.*)$/', $line, $matches)) {
            return null;
        }

        return [
            'pid' => (int) $matches[1],
            'command' => trim($matches[2]),
        ];
    }

    private function matchesBasePath(int $pid, string $basePath): bool
    {
        if ($pid <= 0) {
            return false;
        }

        $cwd = @readlink("/proc/{$pid}/cwd");

        if (!is_string($cwd) || $cwd === '') {
            return false;
        }

        return $cwd === $basePath;
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

    /**
     * @param  array<int, array{pid:int,command:string}>  $processes
     * @return array{pid:int,command:string}|null
     */
    private function workerCommandSample(array $processes): ?array
    {
        if ($processes === []) {
            return null;
        }

        usort($processes, function (array $left, array $right): int {
            $leftScore = count($this->parseWorkerOptions($left['command']));
            $rightScore = count($this->parseWorkerOptions($right['command']));

            if ($leftScore === $rightScore) {
                return strlen($right['command']) <=> strlen($left['command']);
            }

            return $rightScore <=> $leftScore;
        });

        return $processes[0] ?? null;
    }

    /**
     * @return array<string, int>
     */
    private function parseWorkerOptions(string $command): array
    {
        $map = [
            'timeout' => '--timeout',
            'sleep' => '--sleep',
            'tries' => '--tries',
            'memory' => '--memory',
            'backoff' => '--backoff',
            'max_time' => '--max-time',
        ];

        $options = [];

        foreach ($map as $key => $flag) {
            if (preg_match('/'.preg_quote($flag, '/').'(?:=|\s+)(\d+)/', $command, $matches) === 1) {
                $options[$key] = (int) $matches[1];
            }
        }

        return $options;
    }

    private function status(
        string $driver,
        bool $workerRunning,
        int $pendingJobs,
        int $staleWaitingJobs,
        int $stuckJobs,
        bool $processedRecently,
        bool $failingJobsRecently,
        int $timeoutOccurrencesRecently,
        int $problemJobsCount,
    ): int
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

        if ($problemJobsCount > 0) {
            return StatusCodes::ERROR;
        }

        if ($timeoutOccurrencesRecently > 0) {
            return StatusCodes::WARN;
        }

        if ($failingJobsRecently) {
            return StatusCodes::WARN;
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
