<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class AppDebugProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->isLocal() || ! $this->context->debug) {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'app_debug_enabled',
            severity: StatusCodes::ERROR,
            title: 'Debug mode enabled',
            message: 'APP_DEBUG is true while the app environment is '.$this->context->environment.'.',
            remediation: 'Set APP_DEBUG=false in non-local environments.',
        ));
    }
}
