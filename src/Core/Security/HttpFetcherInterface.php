<?php

namespace AppRadar\Agent\Core\Security;

interface HttpFetcherInterface
{
    public function get(string $url, float $timeoutSeconds = 3.0): HttpFetchResult;
}
