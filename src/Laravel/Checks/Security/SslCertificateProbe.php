<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\Security\SslIssueMapper;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class SslCertificateProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
        private readonly ?SslIssueMapper $mapper = null,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        $mapper = $this->mapper ?? new SslIssueMapper(
            sslCheckEnabled: $this->context->sslCheckEnabled,
            publicUrl: $this->context->publicUrl,
            environment: $this->context->environment,
            expiryWarnDays: $this->context->sslExpiryWarnDays,
            timeoutSeconds: $this->context->sslTimeoutSeconds,
        );

        return $mapper->probe();
    }
}
