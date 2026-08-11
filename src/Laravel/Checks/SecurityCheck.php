<?php

namespace AppRadar\Agent\Laravel\Checks;

use AppRadar\Agent\Core\Contracts\SecurityProbeInterface;
use AppRadar\Agent\Core\Contracts\StatusCheckInterface;
use AppRadar\Agent\Data\SecurityIssueCollection;
use AppRadar\Agent\Data\SecurityStatus;
use AppRadar\Agent\Laravel\Checks\Security\AppDebugProbe;
use AppRadar\Agent\Laravel\Checks\Security\AppKeyProbe;
use AppRadar\Agent\Laravel\Checks\Security\BootstrapCacheProbe;
use AppRadar\Agent\Laravel\Checks\Security\ComposerAuditProbe;
use AppRadar\Agent\Laravel\Checks\Security\DatabasePasswordProbe;
use AppRadar\Agent\Laravel\Checks\Security\PhpDisplayErrorsProbe;
use AppRadar\Agent\Laravel\Checks\Security\PhpExtensionProbe;
use AppRadar\Agent\Laravel\Checks\Security\PhpVersionEolProbe;
use AppRadar\Agent\Laravel\Checks\Security\PublicSensitiveFilesProbe;
use AppRadar\Agent\Laravel\Checks\Security\QueueSyncProbe;
use AppRadar\Agent\Laravel\Checks\Security\RedisPasswordProbe;
use AppRadar\Agent\Laravel\Checks\Security\SessionCookieProbe;
use AppRadar\Agent\Laravel\Checks\Security\SessionDriverProbe;
use AppRadar\Agent\Laravel\Checks\Security\SslCertificateProbe;
use AppRadar\Agent\Laravel\Checks\Security\StatusEndpointExposureProbe;
use AppRadar\Agent\Laravel\Checks\Security\StorageLinkProbe;
use AppRadar\Agent\Laravel\Checks\Security\StorageWritableProbe;
use AppRadar\Agent\Laravel\Checks\Security\TelescopeEnabledProbe;
use AppRadar\Agent\Laravel\Security\LaravelSecurityContext;

class SecurityCheck implements StatusCheckInterface
{
    public function __construct(
        private readonly ?LaravelSecurityContext $context = null,
    ) {
    }

    public function run(): SecurityStatus
    {
        $context = $this->context ?? LaravelSecurityContext::fromLaravel();
        $issues = SecurityIssueCollection::empty();

        foreach ($this->probes($context) as $probe) {
            $issues = $issues->merge($probe->probe());
        }

        return SecurityStatus::fromIssues($issues);
    }

    /**
     * @return array<int, SecurityProbeInterface>
     */
    private function probes(LaravelSecurityContext $context): array
    {
        return [
            new AppDebugProbe($context),
            new AppKeyProbe($context),
            new SessionCookieProbe($context),
            new SessionDriverProbe($context),
            new PhpDisplayErrorsProbe($context),
            new PhpExtensionProbe($context),
            new PublicSensitiveFilesProbe($context),
            new StatusEndpointExposureProbe($context),
            new TelescopeEnabledProbe($context),
            new DatabasePasswordProbe($context),
            new RedisPasswordProbe($context),
            new QueueSyncProbe($context),
            new StorageWritableProbe($context),
            new StorageLinkProbe($context),
            new BootstrapCacheProbe($context),
            new PhpVersionEolProbe($context),
            new SslCertificateProbe($context),
            new ComposerAuditProbe($context),
        ];
    }
}
