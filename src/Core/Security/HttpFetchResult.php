<?php

namespace AppRadar\Agent\Core\Security;

final class HttpFetchResult
{
    public function __construct(
        public readonly bool $reached,
        public readonly int $statusCode,
        public readonly string $body,
        public readonly ?string $message = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->reached && $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
