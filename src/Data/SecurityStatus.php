<?php

namespace AppRadar\Agent\Data;

use AppRadar\Agent\Core\Contracts\StatusSectionInterface;
use AppRadar\Agent\Data\Concerns\InteractsWithPayload;

class SecurityStatus implements StatusSectionInterface
{
    use InteractsWithPayload;

    public function __construct(
        public readonly int $status,
        public readonly int $score,
        public readonly int $issueCount,
        public readonly int $errorCount,
        public readonly int $warnCount,
        public readonly SecurityIssueCollection $issues,
    ) {
    }

    public static function fromIssues(SecurityIssueCollection $issues): self
    {
        return new self(
            status: $issues->worstSeverity(),
            score: (new SecurityScoreCalculator())->fromIssues($issues),
            issueCount: $issues->count(),
            errorCount: $issues->errorCount(),
            warnCount: $issues->warnCount(),
            issues: $issues,
        );
    }

    public static function clean(): self
    {
        return self::fromIssues(SecurityIssueCollection::empty());
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function key(): string
    {
        return 'security';
    }

    public static function label(): string
    {
        return 'Security';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): static
    {
        $rawIssues = is_array($payload['issues'] ?? null) ? $payload['issues'] : [];
        $issues = SecurityIssueCollection::empty();

        foreach ($rawIssues as $rawIssue) {
            if (! is_array($rawIssue)) {
                continue;
            }

            $issues = $issues->merge(SecurityIssueCollection::of(SecurityIssue::fromArray($rawIssue)));
        }

        $computed = self::fromIssues($issues);

        return new self(
            status: self::intValue($payload, 'status', $computed->status),
            score: self::intValue($payload, 'score', $computed->score),
            issueCount: self::intValue($payload, 'issue_count', $computed->issueCount),
            errorCount: self::intValue($payload, 'error_count', $computed->errorCount),
            warnCount: self::intValue($payload, 'warn_count', $computed->warnCount),
            issues: $issues,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'score' => $this->score,
            'issue_count' => $this->issueCount,
            'error_count' => $this->errorCount,
            'warn_count' => $this->warnCount,
            'issues' => array_map(
                static fn (SecurityIssue $issue): array => $issue->toArray(),
                $this->issues->all(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
