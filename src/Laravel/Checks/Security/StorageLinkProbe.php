<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class StorageLinkProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->publicStorageLinkPresent) {
            return SecurityIssueCollection::empty();
        }

        // Only warn when the app is likely serving public disk files.
        if (! in_array($this->context->filesystemDisk, ['public', 'local'], true)) {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'storage_link_missing',
            severity: StatusCodes::WARN,
            title: 'storage:link missing',
            message: 'public/storage is missing. Public disk files may 404.',
            remediation: 'Run php artisan storage:link on deploy.',
        ));
    }
}
