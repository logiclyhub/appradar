<?php

namespace AppRadar\Agent\Tests\Php;

use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Php\Checks\SecurityCheck;
use AppRadar\Agent\Php\Config\DatabaseConnectionConfig;
use AppRadar\Agent\Php\Config\PhpAgentConfig;
use AppRadar\Agent\Php\Config\RedisConnectionConfig;
use AppRadar\Agent\Php\Config\SecuritySettings;
use PHPUnit\Framework\TestCase;

final class SecurityCheckTest extends TestCase
{
    public function test_warns_when_status_endpoint_public_and_ssl_host_missing(): void
    {
        $config = new PhpAgentConfig(
            name: 'Demo',
            environment: 'production',
            onlyLocal: false,
            routePath: 'status',
            statusToken: '',
            database: new DatabaseConnectionConfig(null, null, null, null, null, null, null),
            redis: new RedisConnectionConfig(null, 6379, null, 0, 1.0),
            security: new SecuritySettings(
                publicUrl: null,
                publicPath: null,
                sslCheck: true,
                sslExpiryWarnDays: 14,
                sslTimeoutSeconds: 1.0,
                phpUnsupportedBelow: '8.2.0',
                phpEolBelow: '8.1.0',
            ),
        );

        $status = (new SecurityCheck($config))->run();
        $ids = array_map(static fn ($issue) => $issue->id, $status->issues->all());

        $this->assertContains('status_endpoint_public', $ids);
        $this->assertContains('ssl_host_missing', $ids);
        $this->assertLessThan(100, $status->score);
        $this->assertGreaterThanOrEqual(StatusCodes::WARN, $status->status);
    }
}
