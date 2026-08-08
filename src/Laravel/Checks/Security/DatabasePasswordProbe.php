<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class DatabasePasswordProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->isLocal() || ! $this->context->databasePasswordEmpty) {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'database_empty_password',
            severity: StatusCodes::WARN,
            title: 'Database password empty',
            message: 'The default database connection password is empty in a non-local environment.',
            remediation: 'Set a strong DB password and update .env.',
        ));
    }
}
