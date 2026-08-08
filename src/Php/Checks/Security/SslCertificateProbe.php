<?php

namespace AppRadar\Agent\Php\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\Security\SslIssueMapper;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Php\Config\PhpSecurityContext;

final class SslCertificateProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly PhpSecurityContext $context,
        private readonly ?SslIssueMapper $mapper = null,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        $settings = $this->context->security;
        $mapper = $this->mapper ?? new SslIssueMapper(
            sslCheckEnabled: $settings->sslCheck,
            publicUrl: $settings->publicUrl,
            environment: $this->context->environment,
            expiryWarnDays: $settings->sslExpiryWarnDays,
            timeoutSeconds: $settings->sslTimeoutSeconds,
        );

        return $mapper->probe();
    }
}
