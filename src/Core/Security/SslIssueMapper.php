<?php

namespace AppRadar\Agent\Core\Security;

use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;

final class SslIssueMapper
{
    public function __construct(
        private readonly bool $sslCheckEnabled,
        private readonly ?string $publicUrl,
        private readonly string $environment,
        private readonly int $expiryWarnDays,
        private readonly SslCertificateInspector $inspector = new SslCertificateInspector(),
        private readonly float $timeoutSeconds = 3.0,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if (! $this->sslCheckEnabled) {
            return SecurityIssueCollection::empty();
        }

        $issues = SecurityIssueCollection::empty();
        $scheme = is_string($this->publicUrl) ? strtolower((string) parse_url($this->publicUrl, PHP_URL_SCHEME)) : null;
        $host = is_string($this->publicUrl) ? parse_url($this->publicUrl, PHP_URL_HOST) : null;
        $host = is_string($host) ? $host : null;

        if (! $this->isLocalEnvironment() && $scheme === 'http') {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'app_url_not_https',
                severity: StatusCodes::ERROR,
                title: 'Public URL is not HTTPS',
                message: 'The configured public URL uses http:// in a non-local environment.',
                remediation: 'Set the public URL to https:// and terminate TLS at the edge.',
            )));
        }

        if ($host === null || $host === '') {
            return $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'ssl_host_missing',
                severity: StatusCodes::WARN,
                title: 'SSL host missing',
                message: 'No usable public host was configured for the TLS certificate check.',
                remediation: 'Set APP_URL (Laravel) or security.public_url (plain PHP) to your https public URL.',
            )));
        }

        $result = $this->inspector->inspect($host, 443, $this->timeoutSeconds);

        return $issues->merge($this->mapResult($result));
    }

    public function mapResult(SslCertificateResult $result): SecurityIssueCollection
    {
        if (! $result->reached) {
            return SecurityIssueCollection::of(new SecurityIssue(
                id: 'ssl_unreachable',
                severity: StatusCodes::WARN,
                title: 'SSL endpoint unreachable',
                message: 'Could not open a TLS connection to '.$result->host.':443'
                    .($result->message ? ' ('.$result->message.')' : '').'.',
                remediation: 'Ensure the public hostname resolves and port 443 accepts TLS from this server.',
            ));
        }

        $issues = SecurityIssueCollection::empty();

        if ($result->expired) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'ssl_certificate_expired',
                severity: StatusCodes::ERROR,
                title: 'TLS certificate expired',
                message: 'Certificate for '.$result->host.' has expired'
                    .($result->validTo ? ' (valid to '.$result->validTo.')' : '').'.',
                remediation: 'Renew the TLS certificate immediately.',
            )));
        } elseif ($result->daysRemaining !== null && $result->daysRemaining <= $this->expiryWarnDays) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'ssl_certificate_expiring_soon',
                severity: StatusCodes::WARN,
                title: 'TLS certificate expiring soon',
                message: 'Certificate for '.$result->host.' expires in '.$result->daysRemaining.' day(s).',
                remediation: 'Renew the certificate before expiry.',
            )));
        }

        if (! $result->hostnameMatches) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'ssl_hostname_mismatch',
                severity: StatusCodes::ERROR,
                title: 'TLS hostname mismatch',
                message: 'Certificate does not match host '.$result->host.'.',
                remediation: 'Issue a certificate that includes this hostname in CN/SAN.',
            )));
        }

        if (! $result->verified && ! $result->expired && $result->hostnameMatches) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'ssl_certificate_invalid',
                severity: StatusCodes::ERROR,
                title: 'TLS certificate invalid',
                message: 'Certificate for '.$result->host.' could not be verified'
                    .($result->message ? ' ('.$result->message.')' : '').'.',
                remediation: 'Use a certificate from a trusted CA with a complete chain.',
            )));
        }

        return $issues;
    }

    private function isLocalEnvironment(): bool
    {
        return in_array(strtolower($this->environment), ['local', 'testing'], true);
    }
}
