<?php

namespace AppRadar\Agent\Laravel\Checks;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Data\MaintenanceIssueCollection;
use AppRadar\Agent\Data\MaintenanceRuntime;
use AppRadar\Agent\Data\MaintenanceStatus;
use AppRadar\Agent\Laravel\Checks\Maintenance\ComposerAdvisoryProbe;
use AppRadar\Agent\Laravel\Checks\Maintenance\LaravelVersionProbe;
use AppRadar\Agent\Laravel\Checks\Maintenance\PhpVersionProbe;
use Illuminate\Foundation\Application;

final class MaintenanceCheck implements StatusCheckInterface
{
    public function run(): MaintenanceStatus
    {
        $issues = MaintenanceIssueCollection::empty();
        foreach ([
            new PhpVersionProbe(PHP_VERSION, (string) config('appradar.maintenance.php_unsupported_below', '8.2.0'), (string) config('appradar.maintenance.php_eol_below', '8.1.0')),
            new LaravelVersionProbe(Application::VERSION, array_map('intval', (array) config('appradar.maintenance.laravel_security_supported_majors', [11, 12]))),
            new ComposerAdvisoryProbe(base_path(), (bool) config('appradar.maintenance.composer_audit', true), (int) config('appradar.maintenance.composer_audit_cache_seconds', 86400), storage_path(rtrim((string) config('appradar.storage_path', 'app/status'), '/').'/maintenance')),
        ] as $probe) $issues = $issues->merge($probe->probe());
        return MaintenanceStatus::fromIssues($issues, new MaintenanceRuntime(PHP_VERSION, Application::VERSION));
    }
}
