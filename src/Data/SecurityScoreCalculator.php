<?php

namespace AppRadar\Agent\Data;

final class SecurityScoreCalculator
{
    public function fromIssues(SecurityIssueCollection $issues): int
    {
        return max(0, 100 - ($issues->errorCount() * 20) - ($issues->warnCount() * 5));
    }
}
