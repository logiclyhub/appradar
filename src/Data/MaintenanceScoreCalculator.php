<?php

namespace AppRadar\Agent\Data;

final class MaintenanceScoreCalculator
{
    public function fromIssues(MaintenanceIssueCollection $issues): int
    {
        return max(0, 100 - ($issues->errorCount() * 20) - ($issues->warnCount() * 5));
    }
}
