<?php

namespace AppRadar\Agent\Php\Checks;

use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Data\MaintenanceIssueCollection;
use AppRadar\Agent\Data\MaintenanceRuntime;
use AppRadar\Agent\Data\MaintenanceStatus;
use AppRadar\Agent\Php\Config\PhpAgentConfig;
use AppRadar\Agent\Php\Checks\Maintenance\PhpVersionProbe;
use AppRadar\Agent\Laravel\Support\ComposerAuditRunner;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\MaintenanceIssue;

final class MaintenanceCheck implements StatusCheckInterface
{
    public function __construct(private readonly PhpAgentConfig $config) {}
    public function run(): MaintenanceStatus
    {
        $issues = (new PhpVersionProbe(PHP_VERSION, $this->config->security->phpUnsupportedBelow, $this->config->security->phpEolBelow))->probe();
        if ($this->config->maintenanceComposerAudit && $this->config->basePath !== null && is_file($this->config->basePath.'/composer.lock')) {
            $result = (new ComposerAuditRunner())->run($this->config->basePath, 30, $this->config->maintenanceComposerAuditCacheSeconds);
            $issues = $issues->merge($result->ran ? $result->issues : \AppRadar\Agent\Data\MaintenanceIssueCollection::of(new MaintenanceIssue('composer_audit_unavailable', StatusCodes::WARN, 'Composer audit unavailable', $result->message ?? 'composer audit could not be executed.')));
        }
        return MaintenanceStatus::fromIssues($issues, new MaintenanceRuntime(PHP_VERSION));
    }
}
