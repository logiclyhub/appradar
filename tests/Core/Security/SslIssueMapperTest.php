<?php

namespace AppRadar\Agent\Tests\Core\Security;

use AppRadar\Agent\Core\Security\SslCertificateResult;
use AppRadar\Agent\Core\Security\SslIssueMapper;
use AppRadar\Agent\Core\StatusCodes;
use PHPUnit\Framework\TestCase;

final class SslIssueMapperTest extends TestCase
{
    public function test_expired_cert_maps_to_error_issue(): void
    {
        $mapper = new SslIssueMapper(
            sslCheckEnabled: true,
            publicUrl: 'https://example.com',
            environment: 'production',
            expiryWarnDays: 14,
        );

        $result = new SslCertificateResult(
            host: 'example.com',
            reached: true,
            verified: false,
            hostnameMatches: true,
            expired: true,
            daysRemaining: -3,
            validFrom: null,
            validTo: null,
            message: 'certificate has expired',
        );

        $issues = $mapper->mapResult($result);

        $this->assertSame('ssl_certificate_expired', $issues->first()?->id);
        $this->assertSame(StatusCodes::ERROR, $issues->first()?->severity);
    }

    public function test_http_app_url_in_production_is_error(): void
    {
        $mapper = new SslIssueMapper(
            sslCheckEnabled: true,
            publicUrl: 'http://api.example.com',
            environment: 'production',
            expiryWarnDays: 14,
        );

        // Force host-missing path after scheme check by using a host we won't actually connect:
        // probe() will also try TLS; for unit isolation we only assert scheme issue exists when
        // host is present — use map via probe with ssl disabled after constructing issues manually.
        $issues = $mapper->probe();

        $ids = array_map(static fn ($issue) => $issue->id, $issues->all());
        $this->assertContains('app_url_not_https', $ids);
    }

    public function test_ssl_disabled_returns_empty(): void
    {
        $mapper = new SslIssueMapper(
            sslCheckEnabled: false,
            publicUrl: 'http://api.example.com',
            environment: 'production',
            expiryWarnDays: 14,
        );

        $this->assertSame(0, $mapper->probe()->count());
    }
}
