<?php

namespace AppRadar\Agent\Core\Contracts;

use AppRadar\Agent\Data\MaintenanceIssueCollection;

interface MaintenanceProbeInterface
{
    public function probe(): MaintenanceIssueCollection;
}
