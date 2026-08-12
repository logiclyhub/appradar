<?php

namespace AppRadar\Agent\Php\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Php\Config\PhpSecurityContext;

final class StatusEndpointExposureProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly PhpSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->statusTokenConfigured) {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'status_endpoint_unprotected',
            severity: StatusCodes::ERROR,
            title: 'Status endpoint unprotected',
            message: 'APPRADAR_SECRET is empty, so the status endpoint is publicly readable without authentication.',
            remediation: 'Set APPRADAR_SECRET and send it as Authorization: Bearer <secret> (or X-AppRadar-Token).',
        ));
    }
}
