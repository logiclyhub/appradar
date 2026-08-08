<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class SessionCookieProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        if ($this->context->isLocal()) {
            return SecurityIssueCollection::empty();
        }

        $issues = SecurityIssueCollection::empty();

        if (! $this->context->sessionSecure) {
            $severity = strtolower($this->context->environment) === 'production'
                ? StatusCodes::ERROR
                : StatusCodes::WARN;

            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'session_secure_cookie_disabled',
                severity: $severity,
                title: 'Secure session cookies disabled',
                message: 'session.secure is false in environment '.$this->context->environment.'.',
                remediation: 'Set SESSION_SECURE_COOKIE=true behind HTTPS.',
            )));
        }

        if (! $this->context->sessionHttpOnly) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'session_http_only_disabled',
                severity: StatusCodes::ERROR,
                title: 'HttpOnly session cookies disabled',
                message: 'session.http_only is false.',
                remediation: 'Set SESSION_HTTP_ONLY=true.',
            )));
        }

        $sameSite = strtolower((string) $this->context->sessionSameSite);
        if ($sameSite === 'none' && ! $this->context->sessionSecure) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'session_same_site_none_insecure',
                severity: StatusCodes::WARN,
                title: 'SameSite=None without Secure',
                message: 'Session SameSite is none while the Secure flag is off.',
                remediation: 'Enable SESSION_SECURE_COOKIE or avoid SameSite=None.',
            )));
        }

        return $issues;
    }
}
