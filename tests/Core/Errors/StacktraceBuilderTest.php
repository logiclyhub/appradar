<?php

namespace AppRadar\Agent\Tests\Core\Errors;

use AppRadar\Agent\Core\Errors\StacktraceBuilder;
use PHPUnit\Framework\TestCase;

final class StacktraceBuilderTest extends TestCase
{
    public function test_builds_frames_with_in_app_flag(): void
    {
        $stacktrace = (new StacktraceBuilder('/app', maxFrames: 50))->fromTrace([
            [
                'file' => '/app/app/Services/Foo.php',
                'line' => 42,
                'function' => 'bar',
                'class' => 'App\\Services\\Foo',
                'type' => '::',
            ],
            [
                'file' => '/app/vendor/laravel/framework/src/Illuminate/Foo.php',
                'line' => 10,
                'function' => 'handle',
                'class' => 'Illuminate\\Foo',
                'type' => '->',
            ],
        ]);

        $this->assertCount(2, $stacktrace->frames());
        $this->assertTrue($stacktrace->frames()[0]->inApp);
        $this->assertSame('app/Services/Foo.php', $stacktrace->frames()[0]->filename);
        $this->assertSame('App\\Services\\Foo::bar', $stacktrace->frames()[0]->function);
        $this->assertFalse($stacktrace->frames()[1]->inApp);
    }

    public function test_caps_frame_count(): void
    {
        $trace = [];
        for ($i = 0; $i < 60; $i++) {
            $trace[] = [
                'file' => "/app/app/File{$i}.php",
                'line' => $i,
                'function' => 'run',
            ];
        }

        $stacktrace = (new StacktraceBuilder('/app', maxFrames: 50))->fromTrace($trace);

        $this->assertCount(50, $stacktrace->frames());
    }
}
