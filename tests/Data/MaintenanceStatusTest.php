<?php

namespace AppRadar\Agent\Tests\Data;

use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\MaintenanceIssue;
use AppRadar\Agent\Data\MaintenanceIssueCollection;
use AppRadar\Agent\Data\MaintenanceStatus;
use PHPUnit\Framework\TestCase;

final class MaintenanceStatusTest extends TestCase
{
    public function test_score_uses_errors_and_warnings(): void
    {
        $status = MaintenanceStatus::fromIssues(MaintenanceIssueCollection::of(
            new MaintenanceIssue('a', StatusCodes::ERROR, 'A', 'error'),
            new MaintenanceIssue('b', StatusCodes::WARN, 'B', 'warning'),
        ));

        $this->assertSame(StatusCodes::ERROR, $status->status);
        $this->assertSame(75, $status->score);
    }

    public function test_clean_status_is_100_and_round_trips_runtime(): void
    {
        $status = MaintenanceStatus::clean(new \AppRadar\Agent\Data\MaintenanceRuntime('8.4.1', '12.0.0'));
        $roundTrip = MaintenanceStatus::fromArray($status->toArray());

        $this->assertSame(100, $roundTrip->score);
        $this->assertSame('8.4.1', $roundTrip->runtime?->php);
        $this->assertSame('12.0.0', $roundTrip->runtime?->laravel);
    }
}
