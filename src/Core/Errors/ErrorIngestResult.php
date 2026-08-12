<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorIngestResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $message = null,
        public readonly int $statusCode = 0,
    ) {
    }
}
