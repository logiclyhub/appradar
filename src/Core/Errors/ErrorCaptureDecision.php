<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorCaptureDecision
{
    public function __construct(
        public readonly bool $shouldCapture,
        public readonly ?string $reason = null,
    ) {
    }
}
