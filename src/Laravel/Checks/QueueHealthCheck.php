<?php

namespace AppRadar\Agent\Laravel\Checks;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\QueueProblemJob;
use AppRadar\Agent\Data\QueueStatus;
use AppRadar\Agent\Laravel\Support\QueueMetricsStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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
                problemWindowSeconds: (int) config('appradar.queue.problem_window_seconds', 21600),
                defaultProcessedRecently: $pendingJobs === 0 && $runningJobs === 0,
            );
            $failedJobs = $this->failedJobSnapshot(
                connectionName: $connectionName,
                queueName: $queueName,
                problemWindowSeconds: (int) config('appradar.queue.problem_window_seconds', 21600),
                maxProblemJobs: (int) config('appradar.queue.max_problem_jobs', 5),
            );
            $problemSummary = $this->mergeProblemSignals($activity, $failedJobs);
            $workerRunning = $activeWorkers > 0 || $activity['processed_recently'];

            return new QueueStatus(
                status: $this->status(
                    driver: $driver,
                    workerRunning: $workerRunning,
                    pendingJobs: $pendingJobs,
                    staleWaitingJobs: $staleWaitingJobs,
                    stuckJobs: $stuckJobs,
                    processedRecently: $activity['processed_recently'],
                    failingJobsRecently: $activity['failing_jobs_recently'] || $failedJobs['failed_jobs_recently'],
                    timeoutOccurrencesRecently: $problemSummary['timeout_occurrences_recently'],
                    problemJobsCount: $problemSummary['problem_jobs_count'],
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
                failingJobsRecently: $activity['failing_jobs_recently'] || $failedJobs['failed_jobs_recently'],
                exceptionOccurrencesRecently: $problemSummary['exception_occurrences_recently'],
                timeoutOccurrencesRecently: $problemSummary['timeout_occurrences_recently'],
                problemJobsCount: $problemSummary['problem_jobs_count'],
                problemJobs: collect($problemSummary['problem_jobs'] ?? [])
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

    /**
     * @return array{
     *     failed_jobs_recently:bool,
     *     exception_occurrences_recently:int,
     *     timeout_occurrences_recently:int,
     *     problem_jobs_count:int,
     *     problem_jobs:array<int, array<string, mixed>>
     * }
     */
    private function failedJobSnapshot(string $connectionName, string $queueName, int $problemWindowSeconds, int $maxProblemJobs): array
    {
        $failedDriver = (string) config('queue.failed.driver', '');

        if (!in_array($failedDriver, ['database', 'database-uuids'], true)) {
            return $this->emptyProblemSnapshot();
        }

        $database = (string) config('queue.failed.database', config('database.default'));
        $table = (string) config('queue.failed.table', 'failed_jobs');
        $cutoff = now()->subSeconds($problemWindowSeconds);

        try {
            $rows = DB::connection($database)
                ->table($table)
                ->select(['payload', 'exception', 'failed_at', 'connection', 'queue'])
                ->where('connection', $connectionName)
                ->where('queue', $queueName)
                ->where('failed_at', '>=', $cutoff)
                ->orderByDesc('failed_at')
                ->limit(100)
                ->get();
        } catch (Throwable) {
            return $this->emptyProblemSnapshot();
        }

        if ($rows->isEmpty()) {
            return $this->emptyProblemSnapshot();
        }

        $jobs = $rows
            ->map(fn (object $row): array => $this->failedJobRecord($row))
            ->groupBy('name')
            ->map(function ($records, string $name): array {
                $records = collect($records)->values();
                $latest = $records->first();

                return [
                    'name' => $name,
                    'occurrences' => $records->count(),
                    'failed_occurrences' => $records->count(),
                    'timeout_occurrences' => $records->where('timed_out', true)->count(),
                    'max_attempts_seen' => (int) $records->max('max_attempts_seen'),
                    'first_seen_at' => $records->min('failed_at'),
                    'last_seen_at' => $records->max('failed_at'),
                    'latest_exception_class' => $latest['exception_class'],
                    'latest_exception_message' => $latest['exception_message'],
                ];
            })
            ->sort(function (array $left, array $right): int {
                return [$right['timeout_occurrences'], $right['occurrences'], $right['max_attempts_seen']]
                    <=> [$left['timeout_occurrences'], $left['occurrences'], $left['max_attempts_seen']];
            })
            ->take($maxProblemJobs)
            ->values();

        return [
            'failed_jobs_recently' => true,
            'exception_occurrences_recently' => $jobs->sum('occurrences'),
            'timeout_occurrences_recently' => $jobs->sum('timeout_occurrences'),
            'problem_jobs_count' => $jobs->count(),
            'problem_jobs' => $jobs->all(),
        ];
    }

    /**
     * @param  array{
     *     processed_recently:bool,
     *     failing_jobs_recently:bool,
     *     exception_occurrences_recently:int,
     *     timeout_occurrences_recently:int,
     *     problem_jobs_count:int,
     *     problem_jobs:array<int, array<string, mixed>>
     * }  $activity
     * @param  array{
     *     failed_jobs_recently:bool,
     *     exception_occurrences_recently:int,
     *     timeout_occurrences_recently:int,
     *     problem_jobs_count:int,
     *     problem_jobs:array<int, array<string, mixed>>
     * }  $failedJobs
     * @return array{
     *     exception_occurrences_recently:int,
     *     timeout_occurrences_recently:int,
     *     problem_jobs_count:int,
     *     problem_jobs:array<int, array<string, mixed>>
     * }
     */
    private function mergeProblemSignals(array $activity, array $failedJobs): array
    {
        $merged = collect(array_merge($activity['problem_jobs'] ?? [], $failedJobs['problem_jobs'] ?? []))
            ->filter(fn (mixed $job): bool => is_array($job) && isset($job['name']))
            ->groupBy('name')
            ->map(function ($jobs, string $name): array {
                $jobs = collect($jobs)->values();
                $latest = $jobs
                    ->sortByDesc(fn (array $job) => $job['last_seen_at'] ?? '')
                    ->first();

                return [
                    'name' => $name,
                    'occurrences' => (int) $jobs->max('occurrences'),
                    'failed_occurrences' => (int) $jobs->max('failed_occurrences'),
                    'timeout_occurrences' => (int) $jobs->max('timeout_occurrences'),
                    'max_attempts_seen' => (int) $jobs->max('max_attempts_seen'),
                    'first_seen_at' => $jobs->pluck('first_seen_at')->filter()->sort()->first(),
                    'last_seen_at' => $jobs->pluck('last_seen_at')->filter()->sort()->last(),
                    'latest_exception_class' => $latest['latest_exception_class'] ?? null,
                    'latest_exception_message' => $latest['latest_exception_message'] ?? null,
                ];
            })
            ->sort(function (array $left, array $right): int {
                return [$right['timeout_occurrences'], $right['occurrences'], $right['max_attempts_seen']]
                    <=> [$left['timeout_occurrences'], $left['occurrences'], $left['max_attempts_seen']];
            })
            ->values();

        return [
            'exception_occurrences_recently' => max(
                (int) ($activity['exception_occurrences_recently'] ?? 0),
                (int) ($failedJobs['exception_occurrences_recently'] ?? 0),
                $merged->sum('occurrences'),
            ),
            'timeout_occurrences_recently' => max(
                (int) ($activity['timeout_occurrences_recently'] ?? 0),
                (int) ($failedJobs['timeout_occurrences_recently'] ?? 0),
                $merged->sum('timeout_occurrences'),
            ),
            'problem_jobs_count' => $merged->count(),
            'problem_jobs' => $merged->all(),
        ];
    }

    /**
     * @return array{name:string,failed_at:?string,exception_class:?string,exception_message:?string,timed_out:bool,max_attempts_seen:int}
     */
    private function failedJobRecord(object $row): array
    {
        $payload = json_decode((string) ($row->payload ?? ''), true);
        $payload = is_array($payload) ? $payload : [];
        $exception = (string) ($row->exception ?? '');
        $exceptionClass = $this->exceptionClass($exception);
        $exceptionMessage = $this->exceptionMessage($exception);

        return [
            'name' => $this->failedJobName($payload),
            'failed_at' => $this->failedAt($row->failed_at ?? null),
            'exception_class' => $exceptionClass,
            'exception_message' => $exceptionMessage,
            'timed_out' => $this->timedOutFromText($exceptionClass, $exceptionMessage),
            'max_attempts_seen' => (int) ($payload['maxTries'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function failedJobName(array $payload): string
    {
        $name = $payload['displayName']
            ?? data_get($payload, 'data.commandName')
            ?? $payload['job']
            ?? 'Unknown Job';

        return is_string($name) && $name !== '' ? $name : 'Unknown Job';
    }

    private function exceptionClass(string $exception): ?string
    {
        $line = trim(strtok($exception, "\n") ?: '');

        if ($line === '') {
            return null;
        }

        return trim(strtok($line, ':') ?: $line);
    }

    private function exceptionMessage(string $exception): ?string
    {
        $line = trim(strtok($exception, "\n") ?: '');

        if ($line === '') {
            return null;
        }

        $parts = explode(':', $line, 2);

        return isset($parts[1]) ? trim($parts[1]) : $line;
    }

    private function timedOutFromText(?string $exceptionClass, ?string $exceptionMessage): bool
    {
        return str_contains((string) $exceptionClass, 'TimeoutExceededException')
            || str_contains(strtolower((string) $exceptionMessage), 'timed out');
    }

    private function failedAt(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *     failed_jobs_recently:bool,
     *     exception_occurrences_recently:int,
     *     timeout_occurrences_recently:int,
     *     problem_jobs_count:int,
     *     problem_jobs:array<int, array<string, mixed>>
     * }
     */
    private function emptyProblemSnapshot(): array
    {
        return [
            'failed_jobs_recently' => false,
            'exception_occurrences_recently' => 0,
            'timeout_occurrences_recently' => 0,
            'problem_jobs_count' => 0,
            'problem_jobs' => [],
        ];
    }
}
