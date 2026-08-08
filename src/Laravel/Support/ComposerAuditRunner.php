<?php

namespace AppRadar\Agent\Laravel\Support;

use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use Symfony\Component\Process\Process;
use Throwable;

final class ComposerAuditRunner
{
    public function run(string $basePath, int $timeoutSeconds = 30): ComposerAuditResult
    {
        try {
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
                    issues: SecurityIssueCollection::empty(),
                    message: 'composer audit did not return JSON',
                );
            }

            $issues = SecurityIssueCollection::empty();
            $advisories = $decoded['advisories'] ?? $decoded;

            if (is_array($advisories)) {
                foreach ($advisories as $package => $packageAdvisories) {
                    if (! is_array($packageAdvisories)) {
                        continue;
                    }

                    $packageName = is_string($package) ? $package : 'unknown';
                    $severityLabel = strtolower((string) ($packageAdvisories[0]['severity'] ?? 'high'));
                    $severity = in_array($severityLabel, ['low', 'medium'], true)
                        ? StatusCodes::WARN
                        : StatusCodes::ERROR;

                    $title = is_string($packageAdvisories[0]['title'] ?? null)
                        ? $packageAdvisories[0]['title']
                        : 'Security advisory';

                    $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                        id: 'composer_audit:'.$packageName,
                        severity: $severity,
                        title: 'Composer advisory: '.$packageName,
                        message: $title,
                        remediation: 'Update '.$packageName.' to a patched version (composer update '.$packageName.').',
                    )));
                }
            }

            return new ComposerAuditResult(ran: true, issues: $issues);
        } catch (Throwable $throwable) {
            return new ComposerAuditResult(
                ran: false,
                issues: SecurityIssueCollection::empty(),
                message: $throwable->getMessage(),
            );
        }
    }
}
