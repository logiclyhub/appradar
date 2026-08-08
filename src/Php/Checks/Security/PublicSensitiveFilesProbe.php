<?php

namespace AppRadar\Agent\Php\Checks\Security;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\StatusCodes;
use AppRadar\Agent\Data\SecurityIssue;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Php\Config\PhpSecurityContext;

final class PublicSensitiveFilesProbe implements SecurityProbeInterface
{
    public function __construct(
        private readonly PhpSecurityContext $context,
    ) {
    }

    public function probe(): SecurityIssueCollection
    {
        $publicPath = $this->context->security->publicPath;

        if ($publicPath === null || $publicPath === '') {
            return SecurityIssueCollection::empty();
        }

        $public = rtrim($publicPath, DIRECTORY_SEPARATOR);
        $issues = SecurityIssueCollection::empty();

        if (is_file($public.DIRECTORY_SEPARATOR.'.env')) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'env_file_beside_webroot',
                severity: StatusCodes::ERROR,
                title: '.env exposed in public path',
                message: 'A .env file exists under security.public_path.',
                remediation: 'Remove .env from the web root and rotate secrets.',
            )));
        }

        if (is_dir($public.DIRECTORY_SEPARATOR.'.git')) {
            $issues = $issues->merge(SecurityIssueCollection::of(new SecurityIssue(
                id: 'git_dir_in_public',
                severity: StatusCodes::ERROR,
                title: '.git exposed in public path',
                message: 'A .git directory exists under security.public_path.',
                remediation: 'Remove .git from the web root.',
            )));
        }

        return $issues;
    }
}
