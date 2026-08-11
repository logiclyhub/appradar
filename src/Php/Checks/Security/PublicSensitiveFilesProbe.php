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
        $settings = $this->context->security;
        $checker = $this->checker ?? new SensitiveWebPathChecker(
            timeoutSeconds: $settings->sslTimeoutSeconds,
        );

        $issues = SecurityIssueCollection::empty();

        if (is_string($settings->publicUrl) && trim($settings->publicUrl) !== '') {
            $issues = $issues->merge($checker->probeHttp($settings->publicUrl));
        }

        if (is_string($settings->publicPath) && trim($settings->publicPath) !== '') {
            $issues = $issues->merge($checker->probeDisk($settings->publicPath));
        }

        return $issues;
    }
}
