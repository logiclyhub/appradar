<?php

namespace AppRadar\Agent\Php\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\Security\SensitiveWebPathChecker;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Php\Config\PhpSecurityContext;

final class PublicSensitiveFilesProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly PhpSecurityContext $context,
        private readonly ?SensitiveWebPathChecker $checker = null,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        $publicUrl = $this->context->security->publicUrl;

        if (! is_string($publicUrl) || trim($publicUrl) === '') {
            return SecurityIssueCollection::empty();
        }

        $checker = $this->checker ?? new SensitiveWebPathChecker(
            timeoutSeconds: $this->context->security->sslTimeoutSeconds,
        );

        return $checker->probeHttp($publicUrl);
    }
}
