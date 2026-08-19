<?php

namespace AppRadar\Agent\Laravel\Checks\Maintenance;

use AppRadar\Agent\Core\Contracts\MaintenanceProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\MaintenanceIssue;
use AppRadar\Agent\Data\MaintenanceIssueCollection;
use AppRadar\Agent\Laravel\Support\ComposerAuditRunner;

final class ComposerAdvisoryProbe implements MaintenanceProbeInterface
{
    public function __construct(private readonly string $basePath, private readonly bool $enabled, private readonly int $cacheSeconds = 86400, private readonly ?string $cacheDirectory = null, private readonly ComposerAuditRunner $runner = new ComposerAuditRunner()) {}
    public function probe(): MaintenanceIssueCollection
    {
        if (! $this->enabled) return MaintenanceIssueCollection::empty();
        $result = $this->runner->run($this->basePath, 30, $this->cacheSeconds, $this->cacheDirectory);
        if (! $result->ran) return MaintenanceIssueCollection::of(new MaintenanceIssue('composer_audit_unavailable', StatusCodes::WARN, 'Composer audit unavailable', $result->message ?? 'composer audit could not be executed.', 'Ensure Composer is installed and the audit check is enabled only where it can run.'));
        return $result->issues;
    }
}
