<?php

namespace AppRadar\Agent\Laravel\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

final class PublicSensitiveFilesProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly LaravelSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        $public = rtrim($this->context->publicPath, DIRECTORY_SEPARATOR);
        $issues = SecurityIssueCollection::empty();

        if (is_file($public.DIRECTORY_SEPARATOR.'.env')) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'env_file_in_public',
                severity: StatusCodes::ERROR,
                title: '.env exposed in public',
                message: 'A .env file exists under the public web root.',
                remediation: 'Remove public/.env immediately and rotate secrets.',
            )));
        }

        if (is_dir($public.DIRECTORY_SEPARATOR.'.git')) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'git_dir_in_public',
                severity: StatusCodes::ERROR,
                title: '.git exposed in public',
                message: 'A .git directory exists under the public web root.',
                remediation: 'Remove public/.git and ensure the web root is only the public/ folder.',
            )));
        }

        return $issues;
    }
}
