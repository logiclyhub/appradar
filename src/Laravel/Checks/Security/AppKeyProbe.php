<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class AppKeyProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        $key = $this->context->appKey;

        if ($key === null || trim($key) === '') {
            return SecurityIssueCollection::of(new SecurityIssue(
                id: 'app_key_missing',
                severity: StatusCodes::ERROR,
                title: 'Application key missing',
                message: 'APP_KEY is empty or missing.',
                remediation: 'Run php artisan key:generate and keep APP_KEY secret.',
            ));
        }

        $raw = $key;
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $raw = is_string($decoded) ? $decoded : '';
        }

        if (strlen($raw) < 16 || str_contains(strtolower($key), 'changeme') || str_contains(strtolower($key), 'insecure')) {
            return SecurityIssueCollection::of(new SecurityIssue(
                id: 'app_key_insecure_length',
                severity: StatusCodes::WARN,
                title: 'Application key looks weak',
                message: 'APP_KEY appears short or placeholder-like.',
                remediation: 'Generate a fresh APP_KEY with php artisan key:generate.',
            ));
        }

        return SecurityIssueCollection::empty();
    }
}
