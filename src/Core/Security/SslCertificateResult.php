<?php

namespace AppRadar\Agent\Core\Security;

final class SslCertificateResult
{
    public function __construct(
        public readonly string $host,
        public readonly bool $reached,
        public readonly bool $verified,
        public readonly bool $hostnameMatches,
        public readonly bool $expired,
        public readonly ?int $daysRemaining,
        public readonly ?string $validFrom,
        public readonly ?string $validTo,
        public readonly ?string $message = null,
    ) {
    }
}
