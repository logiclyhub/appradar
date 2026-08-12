<?php

namespace AppRadar\Agent\Tests\Core\Errors;

use AppRadar\Agent\Core\Errors\ErrorAppContext;
use AppRadar\Agent\Core\Errors\ErrorEvent;
use AppRadar\Agent\Core\Errors\ErrorExceptionInfo;
use AppRadar\Agent\Core\Errors\ErrorFingerprint;
use AppRadar\Agent\Core\Errors\ErrorRequestContext;
use AppRadar\Agent\Core\Errors\StackFrame;
use AppRadar\Agent\Core\Errors\Stacktrace;
use PHPUnit\Framework\TestCase;

final class ErrorEventTest extends TestCase
{
    public function test_to_array_matches_v1_contract_shape(): void
    {
        $event = new ErrorEvent(
            schemaVersion: 1,
            sentAt: '2026-08-12T08:00:00+00:00',
            app: new ErrorAppContext(
                environment: 'production',
                release: '1.4.2',
                runtime: 'php',
                runtimeVersion: '8.3.12',
                framework: 'laravel',
                frameworkVersion: '12.0.0',
            ),
            eventId: 'evt-1',
            timestamp: '2026-08-12T07:59:59+00:00',
            level: 'error',
            fingerprint: new ErrorFingerprint(['TypeError', 'App\\Foo::bar', 'app/Foo.php:1']),
            exception: new ErrorExceptionInfo(
                type: 'TypeError',
                message: 'boom',
                stacktrace: new Stacktrace([
                    new StackFrame(
                        filename: 'app/Foo.php',
                        absPath: '/app/app/Foo.php',
                        lineno: 1,
                        function: 'App\\Foo::bar',
                        inApp: true,
                    ),
                ]),
            ),
            request: new ErrorRequestContext(
                method: 'GET',
                url: 'https://example.com',
                headers: ['user-agent' => 'test'],
                context: [],
            ),
            queue: false,
        );

        $payload = $event->toArray();

        $this->assertSame(1, $payload['schema_version']);
        $this->assertSame('production', $payload['app']['environment']);
        $this->assertSame('evt-1', $payload['event']['event_id']);
        $this->assertSame(['TypeError', 'App\\Foo::bar', 'app/Foo.php:1'], $payload['event']['fingerprint']);
        $this->assertSame('TypeError', $payload['event']['exception']['type']);
        $this->assertTrue($payload['event']['exception']['stacktrace']['frames'][0]['in_app']);
        $this->assertFalse($payload['event']['tags']['queue']);
        $this->assertSame([], $payload['event']['breadcrumbs']);
    }
}
