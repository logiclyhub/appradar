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
        $checker = $this->checker ?? new SensitiveWebPathChecker(
            timeoutSeconds: $this->context->sslTimeoutSeconds,
        );

        $issues = SecurityIssueCollection::empty();

        if (is_string($this->context->publicUrl) && trim($this->context->publicUrl) !== '') {
            $issues = $issues->merge($checker->probeHttp($this->context->publicUrl));
        }

        // Disk presence is warn-only; HTTP confirmation above is the real error.
        $issues = $issues->merge($checker->probeDisk($this->context->publicPath));

        return $issues;
    }
}
