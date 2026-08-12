<?php

namespace AppRadar\Agent\Core\Errors;

interface ErrorIngestTransportInterface
{
    /**
     * @param  array<string, string>  $headers
     */
    public function post(string $url, string $body, array $headers, float $timeoutSeconds): ErrorIngestTransportResult;
}
