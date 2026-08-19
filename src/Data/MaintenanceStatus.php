<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Core\Contracts\StatusSectionInterface;
use AppRadar\Agent\Data\Concerns\InteractsWithPayload;

final class MaintenanceStatus implements StatusSectionInterface
{
    use InteractsWithPayload;

    public function __construct(
        public readonly int $status,
        public readonly int $score,
        public readonly int $issueCount,
        public readonly int $errorCount,
        public readonly int $warnCount,
        public readonly MaintenanceIssueCollection $issues,
        public readonly ?MaintenanceRuntime $runtime = null,
    ) {}

    public static function fromIssues(MaintenanceIssueCollection $issues, ?MaintenanceRuntime $runtime = null): self
    {
        return new self($issues->worstSeverity(), (new MaintenanceScoreCalculator())->fromIssues($issues), $issues->count(), $issues->errorCount(), $issues->warnCount(), $issues, $runtime);
    }

    public static function clean(?MaintenanceRuntime $runtime = null): self { return self::fromIssues(MaintenanceIssueCollection::empty(), $runtime); }
    public function status(): int { return $this->status; }
    public static function key(): string { return 'maintenance'; }
    public static function label(): string { return 'Maintenance'; }

    public static function fromArray(array $payload): static
    {
        $issues = MaintenanceIssueCollection::empty();
        foreach (is_array($payload['issues'] ?? null) ? $payload['issues'] : [] as $rawIssue) {
            if (is_array($rawIssue)) $issues = $issues->merge(MaintenanceIssueCollection::of(MaintenanceIssue::fromArray($rawIssue)));
        }
        $computed = self::fromIssues($issues, is_array($payload['runtime'] ?? null) ? MaintenanceRuntime::fromArray($payload['runtime']) : null);
        return new self(self::intValue($payload, 'status', $computed->status), self::intValue($payload, 'score', $computed->score), self::intValue($payload, 'issue_count', $computed->issueCount), self::intValue($payload, 'error_count', $computed->errorCount), self::intValue($payload, 'warn_count', $computed->warnCount), $issues, $computed->runtime);
    }

    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status, 'score' => $this->score, 'issue_count' => $this->issueCount,
            'error_count' => $this->errorCount, 'warn_count' => $this->warnCount,
            'issues' => array_map(static fn (MaintenanceIssue $i): array => $i->toArray(), $this->issues->all()),
            'runtime' => $this->runtime?->toArray(),
        ], static fn (mixed $value): bool => $value !== null);
    }
    public function jsonSerialize(): array { return $this->toArray(); }
}
