<?php

namespace AppRadar\Agent\Tests\Php;

use AppRadar\Agent\Php\Config\PhpAgentConfig;
use PHPUnit\Framework\TestCase;

final class PhpAgentConfigBasePathTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir().'/appradar-basepath-'.uniqid('', true);
        mkdir($this->tempRoot.'/config', 0755, true);
        file_put_contents($this->tempRoot.'/composer.lock', "{}");
        file_put_contents($this->tempRoot.'/config/appradar.php', <<<'PHP'
<?php
return [
    'app' => ['name' => 'Test', 'environment' => 'testing'],
    'database' => [],
    'redis' => [],
    'security' => [],
];
PHP);
    }

    protected function tearDown(): void
    {
        @unlink($this->tempRoot.'/config/appradar.php');
        @unlink($this->tempRoot.'/composer.lock');
        @rmdir($this->tempRoot.'/config');
        @rmdir($this->tempRoot);
        parent::tearDown();
    }

    public function test_resolves_project_root_when_config_lives_in_config_directory(): void
    {
        $config = PhpAgentConfig::fromFile($this->tempRoot.'/config/appradar.php');

        $this->assertSame(
            realpath($this->tempRoot),
            realpath((string) $config->basePath),
        );
        $this->assertFileExists($config->basePath.'/composer.lock');
    }

    public function test_explicit_base_path_wins(): void
    {
        $custom = $this->tempRoot.'/custom-root';
        mkdir($custom, 0755, true);
        file_put_contents($custom.'/composer.lock', '{}');

        file_put_contents($this->tempRoot.'/config/appradar.php', <<<PHP
<?php
return [
    'base_path' => '{$custom}',
    'app' => ['name' => 'Test', 'environment' => 'testing'],
    'database' => [],
    'redis' => [],
    'security' => [],
];
PHP);

        $config = PhpAgentConfig::fromFile($this->tempRoot.'/config/appradar.php');

        $this->assertSame(realpath($custom), realpath((string) $config->basePath));

        @unlink($custom.'/composer.lock');
        @rmdir($custom);
    }
}
