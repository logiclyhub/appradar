<?php

namespace AppRadar\Agent\Tests\Core\Errors;

use AppRadar\Agent\Core\Errors\ErrorRequestContext;
use AppRadar\Agent\Core\Errors\ErrorScrubber;
use PHPUnit\Framework\TestCase;

final class ErrorScrubberTest extends TestCase
{
    public function test_redacts_sensitive_keys_in_context(): void
    {
        $scrubber = new ErrorScrubber();
        $request = new ErrorRequestContext(
            method: 'POST',
            url: 'https://example.com/checkout?token=abc&page=1',
            headers: [
                'authorization' => 'Bearer secret',
                'user-agent' => 'Mozilla',
                'cookie' => 'session=abc',
            ],
            context: [
                'password' => 'hunter2',
                'user' => 'jane',
                '_token' => 'csrf',
                'api_secret' => 'x',
            ],
        );

        $scrubbed = $scrubber->scrubRequest($request);

        $this->assertSame('https://example.com/checkout?token=%5BREDACTED%5D&page=1', $scrubbed->url);
        $this->assertSame('[REDACTED]', $scrubbed->headers['authorization']);
        $this->assertSame('Mozilla', $scrubbed->headers['user-agent']);
        $this->assertSame('[REDACTED]', $scrubbed->headers['cookie']);
        $this->assertSame('[REDACTED]', $scrubbed->context['password']);
        $this->assertSame('jane', $scrubbed->context['user']);
        $this->assertSame('[REDACTED]', $scrubbed->context['_token']);
        $this->assertSame('[REDACTED]', $scrubbed->context['api_secret']);
    }

    public function test_truncates_long_messages(): void
    {
        $scrubber = new ErrorScrubber(maxMessageLength: 20);
        $message = str_repeat('a', 50);

        $this->assertSame(str_repeat('a', 20).'…', $scrubber->scrubMessage($message));
    }
}
