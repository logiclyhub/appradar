<?php

namespace AppRadar\Agent\Tests;

use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Laravel\Checks\Maintenance\LaravelVersionProbe;
use AppRadar\Agent\Php\Checks\Maintenance\PhpVersionProbe;
use PHPUnit\Framework\TestCase;

final class MaintenanceProbeTest extends TestCase
{
    public function test_unsupported_php_is_warning_and_eol_is_error(): void
    {
        $this->assertSame(StatusCodes::WARN, (new PhpVersionProbe('8.1.99', '8.2.0', '8.1.0'))->probe()->worstSeverity());
        $this->assertSame(StatusCodes::ERROR, (new PhpVersionProbe('8.0.99', '8.2.0', '8.1.0'))->probe()->worstSeverity());
    }

    public function test_supported_laravel_major_has_no_maintenance_issue(): void
    {
        $this->assertSame(0, (new LaravelVersionProbe('12.4.0', [11, 12]))->probe()->count());
        $this->assertSame(StatusCodes::ERROR, (new LaravelVersionProbe('10.48.0', [11, 12]))->probe()->worstSeverity());
    }
}
