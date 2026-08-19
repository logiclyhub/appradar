<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Core\StatusCodes;

final class MaintenanceIssueCollection
{
    public function __construct(private readonly array $issues = []) {}

    public static function empty(): self { return new self(); }

    public static function of(MaintenanceIssue ...$issues): self
    {
        return (new self($issues))->dedupeById();
    }

    public function merge(self $other): self
    {
        return (new self([...$this->issues, ...$other->issues]))->dedupeById();
    }

    public function all(): array { return $this->issues; }
    public function first(): ?MaintenanceIssue { return $this->issues[0] ?? null; }
    public function count(): int { return count($this->issues); }
    public function errorCount(): int { return count(array_filter($this->issues, static fn (MaintenanceIssue $i): bool => $i->severity === StatusCodes::ERROR)); }
    public function warnCount(): int { return count(array_filter($this->issues, static fn (MaintenanceIssue $i): bool => $i->severity === StatusCodes::WARN)); }

    public function worstSeverity(): int
    {
        return array_reduce($this->issues, static fn (int $worst, MaintenanceIssue $issue): int => max($worst, $issue->severity), StatusCodes::OK);
    }

    private function dedupeById(): self
    {
        $unique = [];
        foreach ($this->issues as $issue) {
            $unique[$issue->id] ??= $issue;
        }
        return new self(array_values($unique));
    }
}
