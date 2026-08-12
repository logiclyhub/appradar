<?php

namespace AppRadar\Agent\Tests\Core\Errors;

use AppRadar\Agent\Core\Errors\ErrorAppContext;
use AppRadar\Agent\Core\Errors\ErrorEvent;
use AppRadar\Agent\Core\Errors\ErrorExceptionInfo;
use AppRadar\Agent\Core\Errors\ErrorFingerprint;
use AppRadar\Agent\Core\Errors\ErrorIngestClient;
use AppRadar\Agent\Core\Errors\ErrorIngestTransportInterface;
use AppRadar\Agent\Core\Errors\ErrorIngestTransportResult;
use AppRadar\Agent\Core\Errors\Stacktrace;
use PHPUnit\Framework\TestCase;

final class ErrorIngestClientTest extends TestCase
{
    public function test_send_posts_json_and_returns_ok(): void
    {
        $transport = new class implements ErrorIngestTransportInterface {
            public string $url = '';
            public string $body = '';
            /** @var array<string, string> */
            public array $headers = [];

            public function post(string $url, string $body, array $headers, float $timeoutSeconds): ErrorIngestTransportResult
            {
                $this->url = $url;
                $this->body = $body;
                $this->headers = $headers;

                return new ErrorIngestTransportResult(ok: true, statusCode: 202);
            }
        };

        $client = new ErrorIngestClient(
            endpoint: 'https://appradar.test/ingest/errors',
            token: 'secret-token',
            timeoutSeconds: 2.0,
            transport: $transport,
        );

        $result = $client->send($this->sampleEvent());

        $this->assertTrue($result->ok);
        $this->assertSame('https://appradar.test/ingest/errors', $transport->url);
        $this->assertSame('Bearer secret-token', $transport->headers['Authorization']);
        $this->assertSame('application/json', $transport->headers['Content-Type']);
        $this->assertStringContainsString('"schema_version":1', $transport->body);
    }

    public function test_send_never_throws_when_transport_fails(): void
    {
        $transport = new class implements ErrorIngestTransportInterface {
            public function post(string $url, string $body, array $headers, float $timeoutSeconds): ErrorIngestTransportResult
            {
                throw new \RuntimeException('network down');
            }
        };

        $client = new ErrorIngestClient(
            endpoint: 'https://appradar.test/ingest/errors',
            token: 'secret-token',
            timeoutSeconds: 2.0,
            transport: $transport,
        );

        $result = $client->send($this->sampleEvent());

        $this->assertFalse($result->ok);
        $this->assertSame('network down', $result->message);
    }

    private function sampleEvent(): ErrorEvent
    {
        return new ErrorEvent(
            schemaVersion: 1,
            sentAt: '2026-08-12T08:00:00+00:00',
            app: new ErrorAppContext(
                environment: 'production',
                release: null,
                runtime: 'php',
                runtimeVersion: '8.3.0',
                framework: 'laravel',
                frameworkVersion: null,
            ),
            eventId: 'evt-1',
            timestamp: '2026-08-12T08:00:00+00:00',
            level: 'error',
            fingerprint: new ErrorFingerprint(['RuntimeException']),
            exception: new ErrorExceptionInfo(
                type: 'RuntimeException',
                message: 'boom',
                stacktrace: new Stacktrace([]),
            ),
            request: null,
            queue: false,
        );
    }
}
