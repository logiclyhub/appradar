<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class StatusEndpointExposureProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        // Public reachability is expected for AppRadar polling.
        // The vulnerability is an unprotected endpoint (no shared token).
        if ($this->context->statusTokenConfigured) {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'status_endpoint_unprotected',
            severity: StatusCodes::ERROR,
            title: 'Status endpoint unprotected',
            message: 'APPRADAR_SECRET is empty, so /status is publicly readable without authentication.',
            remediation: 'Set APPRADAR_SECRET and send it as Authorization: Bearer <secret> (or X-AppRadar-Token).',
        ));
    }
}
