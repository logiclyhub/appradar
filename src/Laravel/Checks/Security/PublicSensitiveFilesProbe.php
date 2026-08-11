<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\Security\SensitiveWebPathChecker;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class PublicSensitiveFilesProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
        private readonly ?SensitiveWebPathChecker $checker = null,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if (! is_string($this->context->publicUrl) || trim($this->context->publicUrl) === '') {
            return SecurityIssueCollection::empty();
        }

        $checker = $this->checker ?? new SensitiveWebPathChecker(
            timeoutSeconds: $this->context->sslTimeoutSeconds,
        );

        return $checker->probeHttp($this->context->publicUrl);
    }
}
