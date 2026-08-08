<?php

namespace AppRadar\Agent\Tests\Data;

use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Data\SecurityStatus;
use AppRadar\Agent\Data\StatusReport;
use PHPUnit\Framework\TestCase;

final class SecurityStatusTest extends TestCase
{
    public function test_security_status_worst_severity_wins(): void
    {
        $issues = new SecurityIssueCollection([
            new SecurityIssue('a', StatusCodes::WARN, 'A', 'warn msg'),
            new SecurityIssue('b', StatusCodes::ERROR, 'B', 'error msg', 'fix it'),
        ]);

        $status = SecurityStatus::fromIssues($issues);

        $this->assertSame(StatusCodes::ERROR, $status->status);
        $this->assertSame(75, $status->score);
        $this->assertSame(2, $status->issueCount);
        $this->assertSame(1, $status->errorCount);
        $this->assertSame(1, $status->warnCount);
    }

    public function test_security_meter_is_100_when_clean(): void
    {
        $status = SecurityStatus::fromIssues(SecurityIssueCollection::empty());

        $this->assertSame(StatusCodes::OK, $status->status);
        $this->assertSame(100, $status->score);
    }

    public function test_status_report_round_trips_security(): void
    {
        $report = StatusReport::fromArray([
            'name' => 'App',
            'environment' => 'production',
            'status' => 2,
            'checked_at' => '2026-08-06T12:00:00+00:00',
            'database' => ['status' => 0, 'connected' => true],
            'redis' => ['status' => 0, 'connected' => true],
            'scheduler' => [
                'status' => 0,
                'running' => true,
                'expected_interval_seconds' => 60,
                'registered_crons' => 0,
                'failed_crons_recently' => 0,
                'successful_crons_recently' => 0,
                'running_crons' => 0,
                'slow_crons' => 0,
            ],
            'queue' => [
                'status' => 0,
                'connected' => true,
                'active_workers' => 0,
                'worker_running' => false,
                'pending_jobs' => 0,
                'running_jobs' => 0,
                'stale_waiting_jobs_over_15_minutes' => 0,
                'stuck_jobs_over_1_hour' => 0,
                'processed_recently' => false,
                'failing_jobs_recently' => false,
                'completed_jobs_recently_count' => 0,
                'failed_jobs_recently_count' => 0,
                'exception_occurrences_recently' => 0,
                'timeout_occurrences_recently' => 0,
                'problem_jobs_count' => 0,
                'problem_jobs' => [],
            ],
            'tests' => ['status' => 0, 'has_run' => false, 'coverage_available' => false],
            'security' => [
                'status' => 2,
                'score' => 80,
                'issue_count' => 1,
                'error_count' => 1,
                'warn_count' => 0,
                'issues' => [
                    [
                        'id' => 'app_debug_enabled',
                        'severity' => 2,
                        'title' => 'Debug mode enabled',
                        'message' => 'Debug is on in production.',
                        'remediation' => 'Set APP_DEBUG=false.',
                    ],
                ],
            ],
        ]);

        $this->assertSame('app_debug_enabled', $report->security->issues->first()?->id);
        $this->assertSame(80, $report->security->score);
        $this->assertArrayHasKey('security', $report->toArray());
        $this->assertSame(80, $report->toArray()['security']['score']);
    }

    public function test_missing_security_defaults_to_clean_score_100(): void
    {
        $report = StatusReport::fromArray([
            'name' => 'App',
            'environment' => 'production',
            'status' => 0,
            'database' => ['status' => 0, 'connected' => true],
            'redis' => ['status' => 0, 'connected' => true],
            'scheduler' => [
                'status' => 0,
                'running' => true,
                'expected_interval_seconds' => 60,
                'registered_crons' => 0,
                'failed_crons_recently' => 0,
                'successful_crons_recently' => 0,
                'running_crons' => 0,
                'slow_crons' => 0,
            ],
            'queue' => [
                'status' => 0,
                'connected' => true,
                'active_workers' => 0,
                'worker_running' => false,
                'pending_jobs' => 0,
                'running_jobs' => 0,
                'stale_waiting_jobs_over_15_minutes' => 0,
                'stuck_jobs_over_1_hour' => 0,
                'processed_recently' => false,
                'failing_jobs_recently' => false,
                'completed_jobs_recently_count' => 0,
                'failed_jobs_recently_count' => 0,
                'exception_occurrences_recently' => 0,
                'timeout_occurrences_recently' => 0,
                'problem_jobs_count' => 0,
                'problem_jobs' => [],
            ],
            'tests' => ['status' => 0, 'has_run' => false, 'coverage_available' => false],
        ]);

        $this->assertSame(100, $report->security->score);
        $this->assertSame(StatusCodes::OK, $report->security->status);
    }
}
