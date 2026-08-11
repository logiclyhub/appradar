<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class PhpExtensionProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->missingPhpExtensions === []) {
            return SecurityIssueCollection::empty();
        }

        $list = implode(', ', $this->context->missingPhpExtensions);

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'php_extension_missing',
            severity: StatusCodes::ERROR,
            title: 'Required PHP extensions missing',
            message: 'Missing PHP extension(s): '.$list.'.',
            remediation: 'Install the missing extensions and restart PHP-FPM/Octane.',
        ));
    }
}
