<?php

namespace AppRadar\Agent\Tests\Core\Errors;

use AppRadar\Agent\Core\Errors\ErrorFingerprintBuilder;
use AppRadar\Agent\Core\Errors\StackFrame;
use AppRadar\Agent\Core\Errors\Stacktrace;
use PHPUnit\Framework\TestCase;

final class ErrorFingerprintBuilderTest extends TestCase
{
    public function test_fingerprint_uses_type_and_top_in_app_frame(): void
    {
        $stacktrace = new Stacktrace([
            new StackFrame(
                filename: 'vendor/foo.php',
                absPath: '/app/vendor/foo.php',
                lineno: 1,
                function: 'vendor',
                inApp: false,
            ),
            new StackFrame(
                filename: 'app/Services/Foo.php',
                absPath: '/app/app/Services/Foo.php',
                lineno: 42,
                function: 'App\\Services\\Foo::bar',
                inApp: true,
            ),
        ]);

        $fingerprint = (new ErrorFingerprintBuilder())->build('TypeError', $stacktrace);

        $this->assertSame([
            'TypeError',
            'App\\Services\\Foo::bar',
            'app/Services/Foo.php:42',
        ], $fingerprint->parts());
    }

    public function test_fingerprint_falls_back_when_no_in_app_frame(): void
    {
        $stacktrace = new Stacktrace([
            new StackFrame(
                filename: 'vendor/foo.php',
                absPath: '/app/vendor/foo.php',
                lineno: 9,
                function: 'x',
                inApp: false,
            ),
        ]);

        $fingerprint = (new ErrorFingerprintBuilder())->build('RuntimeException', $stacktrace);

        $this->assertSame([
            'RuntimeException',
            'x',
            'vendor/foo.php:9',
        ], $fingerprint->parts());
    }
}
