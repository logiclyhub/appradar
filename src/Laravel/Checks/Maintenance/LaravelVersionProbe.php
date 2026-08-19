<?php

namespace AppRadar\Agent\Laravel\Checks\Maintenance;

use AppRadar\Agent\Core\Contracts\MaintenanceProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\MaintenanceIssue;
use AppRadar\Agent\Data\MaintenanceIssueCollection;

final class LaravelVersionProbe implements MaintenanceProbeInterface
{
    public function __construct(private readonly string $version, private readonly array $supportedMajors) {}
    public function probe(): MaintenanceIssueCollection
    {
        $major = (int) explode('.', $this->version)[0];
        return in_array($major, $this->supportedMajors, true)
            ? MaintenanceIssueCollection::empty()
            : MaintenanceIssueCollection::of(new MaintenanceIssue('laravel_version_eol', StatusCodes::ERROR, 'Laravel outside security support', "Running Laravel {$major}.x; security support has ended.", 'Upgrade to a Laravel version that still receives security fixes.'));
    }
}
