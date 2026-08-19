<?php

namespace AppRadar\Agent\Laravel\Support;

use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\MaintenanceIssue;
use AppRadar\Agent\Data\MaintenanceIssueCollection;
use Symfony\Component\Process\Process;
use Throwable;

final class ComposerAuditRunner
{
    public function run(string $basePath, int $timeoutSeconds = 30, int $cacheSeconds = 86400, ?string $cacheDirectory = null): ComposerAuditResult
    {
        try {
            $lockPath = rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.lock';
            if (is_file($lockPath)) {
                $cacheDirectory ??= sys_get_temp_dir().DIRECTORY_SEPARATOR.'appradar-maintenance';
                $cacheFile = rtrim($cacheDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.sha1(realpath($lockPath).'|'.filemtime($lockPath).'|'.filesize($lockPath)).'.json';
                if (is_file($cacheFile) && filemtime($cacheFile) !== false && filemtime($cacheFile) + $cacheSeconds >= time()) {
                    $cached = json_decode((string) file_get_contents($cacheFile), true);
                    if (is_array($cached)) return $this->resultFromPayload($cached);
                }
            }
            $process = new Process(['composer', 'audit', '--format=json', '--no-interaction'], $basePath);
            $process->setTimeout($timeoutSeconds);
            $process->run();

            $output = trim($process->getOutput());
            if ($output === '') {
                $output = trim($process->getErrorOutput());
            }

            $decoded = json_decode($output, true);
            if (! is_array($decoded)) {
                return new ComposerAuditResult(
                    ran: false,
                    issues: MaintenanceIssueCollection::empty(),
                    message: 'composer audit did not return JSON',
                );
            }

            $issues = $this->issuesFromPayload($decoded);

            if (isset($cacheFile)) {
                if (! is_dir($cacheDirectory)) @mkdir($cacheDirectory, 0750, true);
                @file_put_contents($cacheFile, json_encode($decoded));
            }

            return new ComposerAuditResult(ran: true, issues: $issues);
        } catch (Throwable $throwable) {
            return new ComposerAuditResult(ran: false, issues: MaintenanceIssueCollection::empty(), message: $throwable->getMessage());
        }
    }

    private function resultFromPayload(array $payload): ComposerAuditResult
    {
        return new ComposerAuditResult(ran: true, issues: $this->issuesFromPayload($payload));
    }

    private function issuesFromPayload(array $decoded): MaintenanceIssueCollection
    {
        $issues = MaintenanceIssueCollection::empty();
        $advisories = $decoded['advisories'] ?? $decoded;

        if (is_array($advisories)) {
            foreach ($advisories as $package => $packageAdvisories) {
                if (! is_array($packageAdvisories)) {
                    continue;
                }

                $packageName = is_string($package) ? $package : 'unknown';
                $severityLabel = strtolower((string) ($packageAdvisories[0]['severity'] ?? 'high'));
                $severity = in_array($severityLabel, ['low', 'medium'], true) ? StatusCodes::WARN : StatusCodes::ERROR;

                $title = is_string($packageAdvisories[0]['title'] ?? null) ? $packageAdvisories[0]['title'] : 'Security advisory';

                $issues = $issues->merge(MaintenanceIssueCollection::of(new MaintenanceIssue(
                    id: 'composer_advisory:'.$packageName,
                    severity: $severity,
                    title: 'Composer advisory: '.$packageName,
                    message: $title,
                    remediation: 'Update '.$packageName.' to a patched version (composer update '.$packageName.').',
                )));
            }
        }

        return $issues;
    }
}
