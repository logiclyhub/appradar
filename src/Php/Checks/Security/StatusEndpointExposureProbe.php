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
        if ($this->context->onlyLocalStatus) {
            return SecurityIssueCollection::empty();
        }

        return SecurityIssueCollection::of(new SecurityIssue(
            id: 'status_endpoint_public',
            severity: StatusCodes::WARN,
            title: 'Status endpoint not local-only',
            message: 'only_local is false, so the status endpoint may be reachable broadly.',
            remediation: 'Set only_local=true and/or protect the endpoint with APPRADAR_STATUS_TOKEN.',
        ));
    }
}
