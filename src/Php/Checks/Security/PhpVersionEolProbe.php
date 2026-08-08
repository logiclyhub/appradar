<?php

namespace AppRadar\Agent\Php\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Php\Config\PhpSecurityContext;

final class PhpVersionEolProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly PhpSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        $version = $this->context->phpVersion;
        $unsupportedBelow = $this->context->security->phpUnsupportedBelow;
        $eolBelow = $this->context->security->phpEolBelow;

        if (version_compare($version, $eolBelow, '<')) {
            return SecurityIssueCollection::of(new SecurityIssue(
                id: 'php_version_eol',
                severity: StatusCodes::ERROR,
                title: 'PHP version end-of-life',
                message: 'Running PHP '.$version.', which is below '.$eolBelow.'.',
                remediation: 'Upgrade PHP to a supported release ('.$unsupportedBelow.'+ recommended).',
            ));
        }

        if (version_compare($version, $unsupportedBelow, '<')) {
            return SecurityIssueCollection::of(new SecurityIssue(
                id: 'php_version_eol',
                severity: StatusCodes::WARN,
                title: 'PHP version below supported floor',
                message: 'Running PHP '.$version.', below the configured floor '.$unsupportedBelow.'.',
                remediation: 'Upgrade PHP to '.$unsupportedBelow.' or newer.',
            ));
        }

        return SecurityIssueCollection::empty();
    }
}
