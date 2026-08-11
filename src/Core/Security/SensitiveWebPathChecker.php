<?php

namespace AppRadar\Agent\Core\Security;

use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;

final class SensitiveWebPathChecker
{
    public function __construct(
        private readonly HttpFetcherInterface $fetcher = new HttpFetcher(),
        private readonly float $timeoutSeconds = 3.0,
    ) {
    }

    public function probeHttp(string $publicUrl): SecurityIssueCollection
    {
        $base = rtrim($publicUrl, '/');
        if ($base === '') {
            return SecurityIssueCollection::empty();
        }

        $issues = SecurityIssueCollection::empty();

        $env = $this->fetcher->get($base.'/.env', $this->timeoutSeconds);
        if ($env->isSuccessful() && $this->looksLikeEnv($env->body)) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'env_file_publicly_reachable',
                severity: StatusCodes::ERROR,
                title: '.env publicly reachable',
                message: 'HTTP GET '.$base.'/.env returned '.$env->statusCode.' with env-like contents.',
                remediation: 'Block web access to .env (or move it outside the web root) and rotate all secrets.',
            )));
        }

        $git = $this->fetcher->get($base.'/.git/HEAD', $this->timeoutSeconds);
        if ($git->isSuccessful() && $this->looksLikeGitHead($git->body)) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'git_dir_publicly_reachable',
                severity: StatusCodes::ERROR,
                title: '.git publicly reachable',
                message: 'HTTP GET '.$base.'/.git/HEAD returned '.$git->statusCode.' with git HEAD contents.',
                remediation: 'Block web access to .git (or remove it from the web root).',
            )));
        }

        return $issues;
    }

    /**
     * Disk presence under the webroot path — warn only (WAF may still block HTTP).
     */
    public function probeDisk(string $publicPath): SecurityIssueCollection
    {
        $public = rtrim($publicPath, DIRECTORY_SEPARATOR);
        if ($public === '') {
            return SecurityIssueCollection::empty();
        }

        $issues = SecurityIssueCollection::empty();

        if (is_file($public.DIRECTORY_SEPARATOR.'.env')) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'env_file_in_public_path',
                severity: StatusCodes::WARN,
                title: '.env present in public path',
                message: 'A .env file exists under the configured public path. It may still be blocked by the web server/WAF.',
                remediation: 'Move .env outside the web root. Prefer confirming it is not HTTP-reachable.',
            )));
        }

        if (is_dir($public.DIRECTORY_SEPARATOR.'.git')) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'git_dir_in_public_path',
                severity: StatusCodes::WARN,
                title: '.git present in public path',
                message: 'A .git directory exists under the configured public path. It may still be blocked by the web server/WAF.',
                remediation: 'Remove .git from the web root (deploy without .git).',
            )));
        }

        return $issues;
    }

    private function looksLikeEnv(string $body): bool
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            return false;
        }

        if (str_contains($trimmed, 'APP_KEY=') || str_contains($trimmed, 'DB_PASSWORD=')) {
            return true;
        }

        return preg_match('/^[A-Z][A-Z0-9_]*=/m', $trimmed) === 1;
    }

    private function looksLikeGitHead(string $body): bool
    {
        $trimmed = trim($body);

        return str_starts_with($trimmed, 'ref: refs/') || preg_match('/^[0-9a-f]{40}$/i', $trimmed) === 1;
    }
}
