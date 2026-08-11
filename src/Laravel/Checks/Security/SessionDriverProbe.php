<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class SessionDriverProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->isLocal() || $this->context->sessionDriver !== 'array') {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'session_driver_array_in_production',
            severity: StatusCodes::WARN,
            title: 'Array session driver in non-local environment',
            message: 'SESSION_DRIVER is array while the environment is '.$this->context->environment.'.',
            remediation: 'Use cookie, file, database, or redis sessions in production.',
        ));
    }
}
