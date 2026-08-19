<?php

namespace AppRadar\Agent\Laravel\Support;

use AppRadar\Agent\Data\MaintenanceIssueCollection;

final class ComposerAuditResult
{
    public function __construct(
        public readonly bool $ran,
        public readonly MaintenanceIssueCollection $issues,
        public readonly ?string $message = null,
    ) {
    }
}
