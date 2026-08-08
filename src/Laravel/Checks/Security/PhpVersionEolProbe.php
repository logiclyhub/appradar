<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class PhpVersionEolProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        $version = $this->context->phpVersion;

        if (version_compare($version, $this->context->phpEolBelow, '<')) {
            return SecurityIssueCollection::of(new SecurityIssue(
                id: 'php_version_eol',
                severity: StatusCodes::ERROR,
                title: 'PHP version end-of-life',
                message: 'Running PHP '.$version.', which is below '.$this->context->phpEolBelow.'.',
                remediation: 'Upgrade PHP to a supported release ('.$this->context->phpUnsupportedBelow.'+ recommended).',
            ));
        }

        if (version_compare($version, $this->context->phpUnsupportedBelow, '<')) {
            return SecurityIssueCollection::of(new SecurityIssue(
                id: 'php_version_eol',
                severity: StatusCodes::WARN,
                title: 'PHP version below supported floor',
                message: 'Running PHP '.$version.', below the configured floor '.$this->context->phpUnsupportedBelow.'.',
                remediation: 'Upgrade PHP to '.$this->context->phpUnsupportedBelow.' or newer.',
            ));
        }

        return SecurityIssueCollection::empty();
    }
}
