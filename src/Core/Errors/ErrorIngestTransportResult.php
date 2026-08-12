<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorIngestTransportResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly int $statusCode = 0,
        public readonly ?string $message = null,
    ) {
    }
}
