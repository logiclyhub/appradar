<?php

namespace AppRadar\Agent\Tests\Core\Errors;

use AppRadar\Agent\Core\AppRadarCloud;
use AppRadar\Agent\Core\Errors\ErrorIgnoreList;
use AppRadar\Agent\Core\Errors\ErrorReportingSettings;
use PHPUnit\Framework\TestCase;

final class ErrorReportingSettingsTest extends TestCase
{
    public function test_builds_fixed_ingest_url_with_uuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $settings = new ErrorReportingSettings(
            baseUrl: AppRadarCloud::BASE_URL,
            appUuid: $uuid,
            secret: 'apr_secret',
            sampleRate: 1.0,
            sendTimeoutSeconds: 2.0,
            environment: null,
            release: null,
            ignoreList: ErrorIgnoreList::empty(),
        );

        $this->assertTrue($settings->isActive());
        $this->assertSame(
            'https://appradar.nl/api/agent/apps/550e8400-e29b-41d4-a716-446655440000/errors',
            $settings->ingestUrl(),
        );
    }

    public function test_inactive_without_app_uuid(): void
    {
        $settings = new ErrorReportingSettings(
            baseUrl: AppRadarCloud::BASE_URL,
            appUuid: '',
            secret: 'apr_secret',
            sampleRate: 1.0,
            sendTimeoutSeconds: 2.0,
            environment: null,
            release: null,
            ignoreList: ErrorIgnoreList::empty(),
        );

        $this->assertFalse($settings->isActive());
    }
}
