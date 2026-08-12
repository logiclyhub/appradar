<?php

namespace AppRadar\Agent\Tests\Core\Errors;

use AppRadar\Agent\Core\Errors\ErrorCapturePolicy;
use AppRadar\Agent\Core\Errors\ErrorIgnoreList;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorCapturePolicyTest extends TestCase
{
    public function test_ignores_configured_exception_classes(): void
    {
        $policy = new ErrorCapturePolicy(
            ignoreList: new ErrorIgnoreList([RuntimeException::class]),
            sampleRate: 1.0,
        );

        $this->assertFalse($policy->shouldCapture(new RuntimeException('x'))->shouldCapture);
        $this->assertFalse($policy->shouldCapture(new class('x') extends RuntimeException {})->shouldCapture);
    }

    public function test_allows_other_exceptions(): void
    {
        $policy = new ErrorCapturePolicy(
            ignoreList: ErrorIgnoreList::empty(),
            sampleRate: 1.0,
        );

        $this->assertTrue($policy->shouldCapture(new RuntimeException('x'))->shouldCapture);
    }

    public function test_sample_rate_zero_skips(): void
    {
        $policy = new ErrorCapturePolicy(
            ignoreList: ErrorIgnoreList::empty(),
            sampleRate: 0.0,
        );

        $this->assertFalse($policy->shouldCapture(new RuntimeException('x'))->shouldCapture);
    }
}
