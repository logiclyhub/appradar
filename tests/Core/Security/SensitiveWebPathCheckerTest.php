<?php

namespace AppRadar\Agent\Tests\Core\Security;

use AppRadar\Agent\Core\Security\HttpFetchResult;
use AppRadar\Agent\Core\Security\HttpFetcherInterface;
use AppRadar\Agent\Core\Security\SensitiveWebPathChecker;
use AppRadar\Agent\Core\StatusCodes;
use PHPUnit\Framework\TestCase;

final class SensitiveWebPathCheckerTest extends TestCase
{
    public function test_http_env_leak_is_error(): void
    {
        $fetcher = new class implements HttpFetcherInterface {
            public function get(string $url, float $timeoutSeconds = 3.0): HttpFetchResult
            {
                if (str_ends_with($url, '/.env')) {
                    return new HttpFetchResult(true, 200, "APP_KEY=base64:test\nDB_PASSWORD=secret\n");
                }

                return new HttpFetchResult(true, 403, 'Forbidden');
            }
        };

        $issues = (new SensitiveWebPathChecker($fetcher))->probeHttp('https://example.com');
        $ids = array_map(static fn ($i) => $i->id, $issues->all());

        $this->assertContains('env_file_publicly_reachable', $ids);
        $this->assertSame(StatusCodes::ERROR, $issues->first()?->severity);
        $this->assertNotContains('git_dir_publicly_reachable', $ids);
    }

    public function test_http_forbidden_reports_no_issue(): void
    {
        $fetcher = new class implements HttpFetcherInterface {
            public function get(string $url, float $timeoutSeconds = 3.0): HttpFetchResult
            {
                return new HttpFetchResult(true, 403, 'Forbidden');
            }
        };

        $issues = (new SensitiveWebPathChecker($fetcher))->probeHttp('https://example.com');

        $this->assertSame(0, $issues->count());
    }

    public function test_git_head_leak_is_error(): void
    {
        $fetcher = new class implements HttpFetcherInterface {
            public function get(string $url, float $timeoutSeconds = 3.0): HttpFetchResult
            {
                if (str_ends_with($url, '/.git/HEAD')) {
                    return new HttpFetchResult(true, 200, "ref: refs/heads/main\n");
                }

                return new HttpFetchResult(true, 404, 'Not Found');
            }
        };

        $issues = (new SensitiveWebPathChecker($fetcher))->probeHttp('https://example.com');

        $this->assertSame('git_dir_publicly_reachable', $issues->first()?->id);
    }
}
