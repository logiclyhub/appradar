<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;
use AppRadar\Agent\Laravel\Support\ComposerAuditRunner;

final class ComposerAuditProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
        private readonly ComposerAuditRunner $runner = new ComposerAuditRunner(),
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if (! $this->context->composerAuditEnabled) {
            return SecurityIssueCollection::empty();
        }

        $result = $this->runner->run($this->context->basePath);

        if (! $result->ran) {
            return SecurityIssueCollection::of(new SecurityIssue(
                id: 'composer_audit_unavailable',
                severity: StatusCodes::WARN,
                title: 'Composer audit unavailable',
                message: $result->message ?? 'composer audit could not be executed.',
                remediation: 'Ensure composer is installed and security.composer_audit is only enabled where audit can run.',
            ));
        }

        return $result->issues;
    }
}
