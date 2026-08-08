<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Core\StatusCodes;

final class SecurityIssueCollection
{
    /** @param array<int, SecurityIssue> $issues */
    public function __construct(
        private readonly array $issues = [],
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public static function of(SecurityIssue ...$issues): self
    {
        return (new self($issues))->dedupeById();
    }

    public function merge(self $other): self
    {
        return (new self([...$this->issues, ...$other->issues]))->dedupeById();
    }

    public function first(): ?SecurityIssue
    {
        return $this->issues[0] ?? null;
    }

    /**
     * @return array<int, SecurityIssue>
     */
    public function all(): array
    {
        return $this->issues;
    }

    public function count(): int
    {
        return count($this->issues);
    }

    public function errorCount(): int
    {
        return count(array_filter(
            $this->issues,
            static fn (SecurityIssue $issue): bool => $issue->severity === StatusCodes::ERROR,
        ));
    }

    public function warnCount(): int
    {
        return count(array_filter(
            $this->issues,
            static fn (SecurityIssue $issue): bool => $issue->severity === StatusCodes::WARN,
        ));
    }

    public function worstSeverity(): int
    {
        if ($this->issues === []) {
            return StatusCodes::OK;
        }

        $worst = StatusCodes::OK;

        foreach ($this->issues as $issue) {
            if ($issue->severity > $worst) {
                $worst = $issue->severity;
            }
        }

        return $worst;
    }

    private function dedupeById(): self
    {
        $unique = [];

        foreach ($this->issues as $issue) {
            if (! isset($unique[$issue->id])) {
                $unique[$issue->id] = $issue;
            }
        }

        return new self(array_values($unique));
    }
}
