<?php

namespace AppRadar\Agent\Tests\Laravel\Security;

use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Laravel\Checks\Security\BootstrapCacheProbe;
use AppRadar\Agent\Laravel\Checks\Security\PhpExtensionProbe;
use AppRadar\Agent\Laravel\Checks\Security\QueueSyncProbe;
use AppRadar\Agent\Laravel\Checks\Security\SessionDriverProbe;
use AppRadar\Agent\Laravel\Checks\Security\StorageLinkProbe;
use AppRadar\Agent\Laravel\Checks\Security\StorageWritableProbe;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;
use PHPUnit\Framework\TestCase;

final class DoctorStyleProbesTest extends TestCase
{
    public function test_php_extension_probe_lists_missing_extensions(): void
    {
        $issues = (new PhpExtensionProbe($this->context(
            missingPhpExtensions: ['redis', 'bcmath'],
        )))->probe();

        $this->assertSame('php_extension_missing', $issues->first()?->id);
        $this->assertSame(StatusCodes::ERROR, $issues->first()?->severity);
        $this->assertStringContainsString('redis', (string) $issues->first()?->message);
    }

    public function test_storage_writable_probe(): void
    {
        $issues = (new StorageWritableProbe($this->context(
            unwritableStoragePaths: ['storage/logs'],
        )))->probe();

        $this->assertSame('storage_not_writable', $issues->first()?->id);
    }

    public function test_storage_link_probe(): void
    {
        $issues = (new StorageLinkProbe($this->context(
            publicStorageLinkPresent: false,
            filesystemDisk: 'public',
        )))->probe();

        $this->assertSame('storage_link_missing', $issues->first()?->id);
        $this->assertSame(StatusCodes::WARN, $issues->first()?->severity);
    }

    public function test_queue_sync_in_production(): void
    {
        $issues = (new QueueSyncProbe($this->context(
            environment: 'production',
            queueDriver: 'sync',
        )))->probe();

        $this->assertSame('queue_sync_in_production', $issues->first()?->id);
        $this->assertSame(StatusCodes::ERROR, $issues->first()?->severity);
    }

    public function test_queue_sync_ignored_locally(): void
    {
        $issues = (new QueueSyncProbe($this->context(
            environment: 'local',
            queueDriver: 'sync',
        )))->probe();

        $this->assertSame(0, $issues->count());
    }

    public function test_bootstrap_cache_missing_in_production(): void
    {
        $issues = (new BootstrapCacheProbe($this->context(
            environment: 'production',
            configCachePresent: false,
        )))->probe();

        $this->assertSame('bootstrap_cache_missing', $issues->first()?->id);
    }

    public function test_session_array_driver_in_production(): void
    {
        $issues = (new SessionDriverProbe($this->context(
            environment: 'production',
            sessionDriver: 'array',
        )))->probe();

        $this->assertSame('session_driver_array_in_production', $issues->first()?->id);
    }

    /**
     * @param  array<int, string>  $missingPhpExtensions
     * @param  array<int, string>  $unwritableStoragePaths
     */
    private function context(
        string $environment = 'production',
        string $sessionDriver = 'file',
        string $queueDriver = 'redis',
        string $filesystemDisk = 'local',
        bool $publicStorageLinkPresent = true,
        bool $configCachePresent = true,
        array $missingPhpExtensions = [],
        array $unwritableStoragePaths = [],
    ): LaravelSecurityContext {
        return new LaravelSecurityContext(
            environment: $environment,
            debug: false,
            appKey: 'base64:dGVzdA==',
            sessionSecure: true,
            sessionHttpOnly: true,
            sessionSameSite: 'lax',
            sessionDriver: $sessionDriver,
            displayErrors: false,
            phpVersion: PHP_VERSION,
            publicPath: '/tmp',
            storagePath: '/tmp',
            onlyLocalStatus: false,
            statusTokenConfigured: true,
            databasePasswordEmpty: false,
            databaseDriver: 'mysql',
            redisConfigured: true,
            redisPasswordEmpty: false,
            queueDriver: $queueDriver,
            cacheDriver: 'redis',
            filesystemDisk: $filesystemDisk,
            publicStorageLinkPresent: $publicStorageLinkPresent,
            configCachePresent: $configCachePresent,
            missingPhpExtensions: $missingPhpExtensions,
            unwritableStoragePaths: $unwritableStoragePaths,
            telescopeEnabled: false,
            composerAuditEnabled: false,
            publicUrl: 'https://example.com',
            sslCheckEnabled: false,
            sslExpiryWarnDays: 14,
            sslTimeoutSeconds: 1.0,
            phpUnsupportedBelow: '8.2.0',
            phpEolBelow: '8.1.0',
            basePath: '/tmp',
        );
    }
}
