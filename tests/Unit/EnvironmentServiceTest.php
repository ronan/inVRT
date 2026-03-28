<?php

namespace Tests\Unit;

use App\Service\EnvironmentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Fixtures\TestProjectFixture;

/**
 * Tests for EnvironmentService
 *
 * Tests profile/environment/device merging and path construction.
 */
class EnvironmentServiceTest extends TestCase
{
    private TestProjectFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new TestProjectFixture();
        $this->fixture->create();
        $this->fixture->setEnvironmentVariable();
    }

    protected function tearDown(): void
    {
        $this->fixture->unsetEnvironmentVariable();
        $this->fixture->cleanup();
    }

    private function init(string $profile = 'anonymous', string $device = 'desktop', string $env = 'local'): array
    {
        return (new EnvironmentService($profile, $device, $env))->initialize(new NullOutput(), true);
    }

    public function testProfileOverridesBaseUrl(): void
    {
        $this->fixture->writeConfig([
            'project' => ['url' => 'https://example.com'],
            'profiles' => ['mobile' => ['url' => 'https://mobile.example.com']],
        ]);

        $env = $this->init('mobile');

        $this->assertEquals('https://mobile.example.com', $env['INVRT_URL']);
    }

    public function testProfileUrlOverridesEnvironmentUrl(): void
    {
        $this->fixture->writeConfig([
            'project' => ['url' => 'https://example.com'],
            'profiles' => ['default' => ['url' => 'https://profile.example.com']],
            'environments' => ['dev' => ['url' => 'https://dev.example.com']],
        ]);

        // Profile is applied after environment, so profile URL wins
        $env = $this->init('default', 'desktop', 'dev');

        $this->assertEquals('https://profile.example.com', $env['INVRT_URL']);
    }

    public function testProfileCredentialsAreResolved(): void
    {
        $this->fixture->writeConfig([
            'project' => ['url' => 'https://example.com'],
            'profiles' => ['admin' => ['username' => 'admin_user', 'password' => 'admin_pass']],
        ]);

        $env = $this->init('admin');

        $this->assertEquals('admin_user', $env['INVRT_USERNAME']);
        $this->assertEquals('admin_pass', $env['INVRT_PASSWORD']);
    }

    public function testProfileCredentialsOverrideEnvironment(): void
    {
        $this->fixture->writeConfig([
            'project' => ['url' => 'https://example.com'],
            'profiles' => ['default' => ['username' => 'profile_user', 'password' => 'profile_pass']],
            'environments' => ['dev' => ['username' => 'env_user', 'password' => 'env_pass']],
        ]);

        // Profile is applied after environment, so profile credentials win
        $env = $this->init('default', 'desktop', 'dev');

        $this->assertEquals('profile_user', $env['INVRT_USERNAME']);
        $this->assertEquals('profile_pass', $env['INVRT_PASSWORD']);
    }

    public function testDeviceIsStored(): void
    {
        $this->fixture->writeConfig(['project' => ['url' => 'https://example.com']]);

        $env = $this->init('anonymous', 'mobile');

        $this->assertEquals('mobile', $env['INVRT_DEVICE']);
    }

    public function testDataDirectoryPathContainsProfileAndEnv(): void
    {
        $this->fixture->writeMinimalConfig();

        $env = $this->init('testers', 'desktop', 'dev');

        $this->assertStringContainsString('testers', $env['INVRT_DATA_DIR']);
        $this->assertStringContainsString('dev', $env['INVRT_DATA_DIR']);
        $this->assertStringContainsString('data', $env['INVRT_DATA_DIR']);
    }

    public function testConfigFilePathIsSet(): void
    {
        $this->fixture->writeMinimalConfig();

        $env = $this->init();

        $this->assertStringContainsString('config.yaml', $env['INVRT_CONFIG_FILE']);
        $this->assertStringContainsString('.invrt', $env['INVRT_CONFIG_FILE']);
    }

    public function testCookiesFilePathContainsProfileAndEnv(): void
    {
        $this->fixture->writeMinimalConfig();

        $env = $this->init('myprofile', 'desktop', 'production');

        $this->assertStringContainsString('myprofile', $env['INVRT_COOKIES_FILE']);
        $this->assertStringContainsString('production', $env['INVRT_COOKIES_FILE']);
    }
}

