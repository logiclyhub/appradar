<?php

namespace AppRadar\Agent\Php\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Php\Config\PhpSecurityContext;

final class RedisPasswordProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly PhpSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->isLocal() || ! $this->context->redisConfigured || ! $this->context->redisPasswordEmpty) {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'redis_empty_password',
            severity: StatusCodes::WARN,
            title: 'Redis password empty',
            message: 'Redis is configured without a password in a non-local environment.',
            remediation: 'Set a Redis password in appradar.php.',
        ));
    }
}
