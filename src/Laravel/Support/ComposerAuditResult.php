<?php

namespace AppRadar\Agent\Laravel\Support;

use AppRadar\Agent\Data\SecurityIssueCollection;

final class ComposerAuditResult
{
    public function __construct(
        public readonly bool $ran,
        public readonly SecurityIssueCollection $issues,
        public readonly ?string $message = null,
    ) {
    }
}
