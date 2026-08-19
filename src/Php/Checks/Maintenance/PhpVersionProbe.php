<?php

namespace AppRadar\Agent\Php\Checks\Maintenance;

use AppRadar\Agent\Core\Contracts\MaintenanceProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\MaintenanceIssue;
use AppRadar\Agent\Data\MaintenanceIssueCollection;

final class PhpVersionProbe implements MaintenanceProbeInterface
{
    public function __construct(private readonly string $version, private readonly string $unsupportedBelow, private readonly string $eolBelow) {}
    public function probe(): MaintenanceIssueCollection
    {
        if (version_compare($this->version, $this->eolBelow, '<')) return MaintenanceIssueCollection::of(new MaintenanceIssue('php_version_eol', StatusCodes::ERROR, 'PHP version end-of-life', "Running PHP {$this->version}, below {$this->eolBelow}.", 'Upgrade PHP to a supported release.'));
        if (version_compare($this->version, $this->unsupportedBelow, '<')) return MaintenanceIssueCollection::of(new MaintenanceIssue('php_version_eol', StatusCodes::WARN, 'PHP version below supported floor', "Running PHP {$this->version}, below {$this->unsupportedBelow}.", "Upgrade PHP to {$this->unsupportedBelow} or newer."));
        return MaintenanceIssueCollection::empty();
    }
}
