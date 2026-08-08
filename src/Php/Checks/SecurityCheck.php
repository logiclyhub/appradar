<?php

namespace AppRadar\Agent\Php\Checks;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Data\SecurityStatus;
use AppRadar\Agent\Php\Checks\Security\DatabasePasswordProbe;
use AppRadar\Agent\Php\Checks\Security\PhpDisplayErrorsProbe;
use AppRadar\Agent\Php\Checks\Security\PhpVersionEolProbe;
use AppRadar\Agent\Php\Checks\Security\PublicSensitiveFilesProbe;
use AppRadar\Agent\Php\Checks\Security\RedisPasswordProbe;
use AppRadar\Agent\Php\Checks\Security\SslCertificateProbe;
use AppRadar\Agent\Php\Checks\Security\StatusEndpointExposureProbe;
use AppRadar\Agent\Php\Config\PhpAgentConfig;
use AppRadar\Agent\Php\Config\PhpSecurityContext;

class SecurityCheck implements StatusCheckInterface
{
    public function __construct(
        private readonly PhpAgentConfig $config,
    ) {
    }

    public function run(): SecurityStatus
    {
        $context = PhpSecurityContext::fromConfig($this->config);
        $issues = SecurityIssueCollection::empty();

        foreach ($this->probes($context) as $probe) {
            $issues = $issues->merge($probe->probe());
        }

        return SecurityStatus::fromIssues($issues);
    }

    /**
     * @return array<int, SecurityProbeInterface>
     */
    private function probes(PhpSecurityContext $context): array
    {
        return [
            new PhpDisplayErrorsProbe($context),
            new PhpVersionEolProbe($context),
            new DatabasePasswordProbe($context),
            new RedisPasswordProbe($context),
            new PublicSensitiveFilesProbe($context),
            new StatusEndpointExposureProbe($context),
            new SslCertificateProbe($context),
        ];
    }
}
