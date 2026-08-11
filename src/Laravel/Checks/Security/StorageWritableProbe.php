<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class StorageWritableProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->unwritableStoragePaths === []) {
            return SecurityIssueCollection::empty();
        }

        $list = implode(', ', $this->context->unwritableStoragePaths);

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'storage_not_writable',
            severity: StatusCodes::ERROR,
            title: 'Storage directories not writable',
            message: 'Laravel cannot write to: '.$list.'.',
            remediation: 'Fix ownership/permissions on storage (and bootstrap/cache) for the web/PHP user.',
        ));
    }
}
