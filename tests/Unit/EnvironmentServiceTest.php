<?php

namespace Tests\Unit;

use App\Input\InvrtConfiguration;
use App\Service\EnvironmentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Fixtures\TestProjectFixture;

/**
 * Tests for EnvironmentService — config merging and env-var construction.
 */
class EnvironmentServiceTest extends TestCase
{
    private TestProjectFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new TestProjectFixture();
        $this->fixture->create();
        $this->fixture->setEnvironmentVariable();
        putenv('INVRT_USERNAME=');
        putenv('INVRT_PASSWORD=');
    }

    protected function tearDown(): void
    {
        $this->fixture->unsetEnvironmentVariable();
        $this->fixture->cleanup();
        putenv('INVRT_USERNAME=');
        putenv('INVRT_PASSWORD=');
    }

    private function init(string $profile = 'anonymous', string $device = 'desktop', string $env = 'local'): array
    {
        return (new EnvironmentService())->initialize($profile, $device, $env, new NullOutput(), true);
    }

    // ── Settings section ──────────────────────────────────────────────────────

    public function testSettingsSectionValuesAreUsedAsBase(): void
    {
        $this->fixture->writeConfig([
            'settings' => ['url' => 'https://settings.example.com', 'max_pages' => 42],
        ]);

        $env = $this->init();

        $this->assertEquals('https://settings.example.com', $env['INVRT_URL']);
        $this->assertEquals('42', $env['INVRT_MAX_PAGES']);
    }

    // ── Environment section ───────────────────────────────────────────────────

    public function testEnvironmentOverridesSettings(): void
    {
        $this->fixture->writeConfig([
            'settings' => ['url' => 'https://settings.example.com'],
            'environments' => ['local' => ['url' => 'http://localhost']],
        ]);

        $env = $this->init();

        $this->assertEquals('http://localhost', $env['INVRT_URL']);
    }

    // ── Profile section ───────────────────────────────────────────────────────

    public function testProfileOverridesEnvironment(): void
    {
        $this->fixture->writeConfig([
            'settings' => ['url' => 'https://settings.example.com'],
            'environments' => ['dev' => ['url' => 'https://dev.example.com']],
            'profiles' => ['admin' => ['url' => 'https://profile.example.com']],
        ]);

        $env = $this->init('admin', 'desktop', 'dev');

        $this->assertEquals('https://profile.example.com', $env['INVRT_URL']);
    }

    public function testProfileCredentialsAreResolved(): void
    {
        $this->fixture->writeConfig([
            'settings' => ['url' => 'https://example.com'],
            'profiles' => ['admin' => ['username' => 'admin_user', 'password' => 'admin_pass']],
        ]);

        $env = $this->init('admin');

        $this->assertEquals('admin_user', $env['INVRT_USERNAME']);
        $this->assertEquals('admin_pass', $env['INVRT_PASSWORD']);
    }

    public function testProfileCredentialsOverrideEnvironment(): void
    {
        $this->fixture->writeConfig([
            'settings' => ['url' => 'https://example.com'],
            'profiles' => ['default' => ['username' => 'profile_user', 'password' => 'profile_pass']],
            'environments' => ['dev' => ['username' => 'env_user', 'password' => 'env_pass']],
        ]);

        $env = $this->init('default', 'desktop', 'dev');

        $this->assertEquals('profile_user', $env['INVRT_USERNAME']);
        $this->assertEquals('profile_pass', $env['INVRT_PASSWORD']);
    }

    // ── Device section ────────────────────────────────────────────────────────

    public function testDeviceOverridesProfile(): void
    {
        $this->fixture->writeConfig([
            'settings' => ['viewport_width' => 1920],
            'profiles' => ['mobile' => ['viewport_width' => 500]],
            'devices' => ['mobile' => ['viewport_width' => 375, 'viewport_height' => 667]],
        ]);

        $env = $this->init('mobile', 'mobile');

        $this->assertEquals('375', $env['INVRT_VIEWPORT_WIDTH']);
    }

    public function testDeviceIsStored(): void
    {
        $this->fixture->writeConfig(['settings' => ['url' => 'https://example.com']]);

        $env = $this->init('anonymous', 'mobile');

        $this->assertEquals('mobile', $env['INVRT_DEVICE']);
    }

    // ── Full precedence chain ─────────────────────────────────────────────────

    public function testFullPrecedenceChain(): void
    {
        $this->fixture->writeConfig([
            'settings' => ['max_pages' => 10],
            'environments' => ['local' => ['max_pages' => 20]],
            'profiles' => ['admin' => ['max_pages' => 30]],
            'devices' => ['mobile' => ['max_pages' => 40]],
        ]);

        $env = $this->init('admin', 'mobile', 'local');

        $this->assertEquals('40', $env['INVRT_MAX_PAGES']);
    }

    // ── All documented env vars present ──────────────────────────────────────

    public function testAllDocumentedEnvVarsPresent(): void
    {
        $this->fixture->writeMinimalConfig();

        $env = $this->init();

        $expected = [
            'INVRT_PROFILE',
            'INVRT_DEVICE',
            'INVRT_ENVIRONMENT',
            'INVRT_SCRIPTS_DIR',
            'INVRT_DIRECTORY',
            'INVRT_DATA_DIR',
            'INVRT_COOKIES_FILE',
            'INVRT_CONFIG_FILE',
            'INVRT_URL',
            'INVRT_LOGIN_URL',
            'INVRT_USERNAME',
            'INVRT_PASSWORD',
            'INVRT_VIEWPORT_WIDTH',
            'INVRT_VIEWPORT_HEIGHT',
            'INVRT_MAX_CRAWL_DEPTH',
            'INVRT_MAX_PAGES',
            'INVRT_USER_AGENT',
            'INVRT_MAX_CONCURRENT_REQUESTS',
        ];

        foreach ($expected as $var) {
            $this->assertArrayHasKey($var, $env, "Missing: $var");
        }
    }

    public function testReturnedVarsMatchConfigKeys(): void
    {
        $this->fixture->writeMinimalConfig();

        $env = $this->init();

        foreach (
            [
                'url',
                'login_url',
                'username',
                'password',
                'viewport_width',
                'viewport_height',
                'max_crawl_depth',
                'max_pages',
                'user_agent',
                'max_concurrent_requests',
            ] as $key
        ) {
            $envKey = 'INVRT_' . strtoupper($key);
            $this->assertArrayHasKey($envKey, $env, "Missing env var for config key: $key");
        }
    }

    // ── Missing sections fall back to defaults ────────────────────────────────

    public function testMissingSectionsFallBackToDefaults(): void
    {
        // Config with no environments/profiles/devices
        $this->fixture->writeConfig(['name' => 'Test']);

        $env = $this->init();

        $this->assertEquals((string) InvrtConfiguration::DEFAULTS['viewport_width'], $env['INVRT_VIEWPORT_WIDTH']);
        $this->assertEquals((string) InvrtConfiguration::DEFAULTS['max_crawl_depth'], $env['INVRT_MAX_CRAWL_DEPTH']);
        $this->assertEquals(InvrtConfiguration::DEFAULTS['user_agent'], $env['INVRT_USER_AGENT']);
    }

    // ── Path construction ─────────────────────────────────────────────────────

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
