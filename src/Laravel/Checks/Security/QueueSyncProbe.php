<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class QueueSyncProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->isLocal() || $this->context->queueDriver !== 'sync') {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'queue_sync_in_production',
            severity: StatusCodes::ERROR,
            title: 'Sync queue in non-local environment',
            message: 'The default queue driver is sync while the environment is '.$this->context->environment.'.',
            remediation: 'Use redis/database/sqs (or similar) and run queue workers in production.',
        ));
    }
}
