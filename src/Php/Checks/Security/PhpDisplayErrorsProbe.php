<?php

namespace AppRadar\Agent\Php\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Php\Config\PhpSecurityContext;

final class PhpDisplayErrorsProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly PhpSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->isLocal() || ! $this->context->displayErrors) {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'php_display_errors_on',
            severity: StatusCodes::ERROR,
            title: 'PHP display_errors enabled',
            message: 'display_errors is on in a non-local environment.',
            remediation: 'Disable display_errors in php.ini for production.',
        ));
    }
}
