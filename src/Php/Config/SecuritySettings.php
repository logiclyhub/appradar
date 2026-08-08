<?php

namespace AppRadar\Agent\Php\Config;

final class SecuritySettings
{
    public function __construct(
        public readonly ?string $publicUrl,
        public readonly ?string $publicPath,
        public readonly bool $sslCheck,
        public readonly int $sslExpiryWarnDays,
        public readonly float $sslTimeoutSeconds,
        public readonly string $phpUnsupportedBelow,
        public readonly string $phpEolBelow,
    ) {
    }
}
