<?php

namespace AppRadar\Agent\Core\Contracts;

use AppRadar\Agent\Data\SecurityIssueCollection;

interface SecurityProbeInterface
{
    public function probe(): SecurityIssueCollection;
}
