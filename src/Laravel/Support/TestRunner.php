<?php

namespace AppRadar\Agent\Laravel\Support;

use Symfony\Component\Process\Process;

class TestRunner
{
    public function __construct(
        private readonly StatusFileStore $store = new StatusFileStore(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(int $timeout = 600): array
    {
        $coverageDriver = $this->coverageDriver();
        $junitPath = $this->store->path('phpunit-junit.xml');
        $coveragePath = $this->store->path('phpunit-coverage.xml');

        $command = [
            PHP_BINARY,
            'vendor/bin/phpunit',
            '--log-junit',
            $junitPath,
        ];

        if ($coverageDriver !== null) {
            $command[] = '--coverage-clover';
            $command[] = $coveragePath;
        }

        $startedAt = microtime(true);
        $process = new Process($command, base_path());
        $process->setTimeout($timeout);
        $process->run();

        $result = [
            'has_run' => true,
            'last_run_at' => now()->toIso8601String(),
            'success' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'duration_seconds' => round(microtime(true) - $startedAt, 2),
            'coverage_available' => $coverageDriver !== null,
            ...$this->parseJunitSummary($junitPath),
            'coverage_percent' => $coverageDriver !== null ? $this->parseCoveragePercent($coveragePath) : null,
        ];

        $this->store->writeJson('test-run.json', $result);

        return $result;
    }

    public function coverageDriver(): ?string
    {
        if (extension_loaded('pcov')) {
            return 'pcov';
        }

        if (extension_loaded('xdebug')) {
            return 'xdebug';
        }

        return null;
    }

    /**
     * @return array{tests:int|null,assertions:int|null,failures:int|null,errors:int|null,skipped:int|null}
     */
    private function parseJunitSummary(string $path): array
    {
        if (!is_file($path)) {
            return $this->emptySummary();
        }

        $xml = @simplexml_load_file($path);

        if ($xml === false) {
            return $this->emptySummary();
        }

        $attributes = $xml->attributes();

        return [
            'tests' => isset($attributes['tests']) ? (int) $attributes['tests'] : null,
            'assertions' => isset($attributes['assertions']) ? (int) $attributes['assertions'] : null,
            'failures' => isset($attributes['failures']) ? (int) $attributes['failures'] : null,
            'errors' => isset($attributes['errors']) ? (int) $attributes['errors'] : null,
            'skipped' => isset($attributes['skipped']) ? (int) $attributes['skipped'] : null,
        ];
    }

    private function parseCoveragePercent(string $path): ?float
    {
        if (!is_file($path)) {
            return null;
        }

        $xml = @simplexml_load_file($path);

        if ($xml === false || !isset($xml->project->metrics)) {
            return null;
        }

        $metrics = $xml->project->metrics->attributes();
        $covered = isset($metrics['coveredstatements']) ? (int) $metrics['coveredstatements'] : null;
        $statements = isset($metrics['statements']) ? (int) $metrics['statements'] : null;

        return ($covered !== null && $statements)
            ? round(($covered / $statements) * 100, 2)
            : null;
    }

    /**
     * @return array{tests:int|null,assertions:int|null,failures:int|null,errors:int|null,skipped:int|null}
     */
    private function emptySummary(): array
    {
        return [
            'tests' => null,
            'assertions' => null,
            'failures' => null,
            'errors' => null,
            'skipped' => null,
        ];
    }
}
