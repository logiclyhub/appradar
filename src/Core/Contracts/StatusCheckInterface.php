<?php

namespace AppRadar\Agent\Core\Contracts;

use AppRadar\Agent\Core\ValueObjects\CheckResult;

interface StatusCheckInterface
{
    public function run(): CheckResult;
}
