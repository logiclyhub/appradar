<?php

namespace AppRadar\Agent\Core\Contracts;

interface StatusCheckInterface
{
    public function run(): StatusSectionInterface;
}
