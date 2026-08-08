<?php

namespace AppRadar\Agent\Php\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Php\Config\PhpSecurityContext;

final class DatabasePasswordProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly PhpSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->isLocal() || ! $this->context->databaseConfigured || ! $this->context->databasePasswordEmpty) {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'database_empty_password',
            severity: StatusCodes::WARN,
            title: 'Database password empty',
            message: 'Database credentials are configured with an empty password in a non-local environment.',
            remediation: 'Set a strong database password in appradar.php.',
        ));
    }
}
