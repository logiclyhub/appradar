<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class TelescopeEnabledProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->isLocal() || ! $this->context->telescopeEnabled) {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'telescope_exposed',
            severity: StatusCodes::WARN,
            title: 'Telescope enabled',
            message: 'Laravel Telescope appears enabled in a non-local environment.',
            remediation: 'Disable Telescope in production or strictly gate access.',
        ));
    }
}
