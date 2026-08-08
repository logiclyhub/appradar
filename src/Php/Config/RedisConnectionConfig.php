<?php

namespace AppRadar\Agent\Php\Config;

final class RedisConnectionConfig
{
    public function __construct(
        public readonly ?string $host,
        public readonly int $port,
        public readonly ?string $password,
        public readonly int $database,
        public readonly float $timeout,
    ) {
    }

    public function isConfigured(): bool
    {
        return is_string($this->host) && trim($this->host) !== '';
    }
}
