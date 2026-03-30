<?php

namespace Tests\Unit;

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
            // Use scratch/tmp/{ClassName}/{testName} for deterministic, inspectable output
        $shortClass = (new \ReflectionClass($this))->getShortName();
        $base = dirname(__DIR__, 2) . '/scratch/tmp/' . $shortClass . '/' . $this->name();

        $this->fixture = new TestProjectFixture($base);
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
        $this->fixture->setEnvironmentVariable();
        return (new EnvironmentService())->initialize($profile, $device, $env, new NullOutput(), true);
    }

    // File handling / config file loading / env var export tests

    public function testInitializeThrowsWhenConfigMissingAndRequired(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->init();
    }

    public function testInitializeReturnsDefaultBaseWhenConfigMissingAndNotRequired(): void
    {
        $config = (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), false);
        $compare = [
            'INVRT_PROFILE' => 'anonymous',
            'INVRT_DEVICE' => 'desktop',
            'INVRT_ENVIRONMENT' => 'local',
            'INVRT_CWD' => $this->fixture->getProjectDir(),
            'INVRT_DIRECTORY' => $this->fixture->getInvrtDir(),
            'INVRT_CONFIG_FILE' => $this->fixture->getInvrtDir() . '/config.yaml',
            'INVRT_DATA_DIR' => $this->fixture->getInvrtDir() . '/data/local/anonymous',
            'INVRT_COOKIES_FILE' => $this->fixture->getInvrtDir() . '/data/local/anonymous/cookies',
            'INVRT_URL' => '',
            'INVRT_LOGIN_URL' => '',
            'INVRT_USERNAME' => '',
            'INVRT_PASSWORD' => '',
            'INVRT_VIEWPORT_WIDTH' => 1024,
            'INVRT_VIEWPORT_HEIGHT' => 768,
            'INVRT_MAX_CRAWL_DEPTH' => 3,
            'INVRT_MAX_PAGES' => 100,
            'INVRT_USER_AGENT' => 'InVRT/1.0',
            'INVRT_MAX_CONCURRENT_REQUESTS' => 5,
            'INVRT_SCRIPTS_DIR' => '',
        ];
        $this->assertSame($compare, $config);
    }


    // ── Settings section ──────────────────────────────────────────────────────

    public function testSettingsSectionValuesAreUsedAsBase(): void
    {
        $this->fixture->writeConfig([
            'settings' => [
                'viewport_width' => 1920,
                'max_pages' => 42,
            ],
            'environments' => [
                'local' => ['url' => 'http://localhost'],
                'dev' => ['url' => 'http://dev.local']
            ],
            'profiles' => [
                'admin' => ['username' => 'admin_user', 'password' => 'admin_pass']
                ],
            'devices' => [
                'mobile' => ['viewport_width' => 375, 'viewport_height' => 667]
            ],
        ]);

        $env = $this->init();

        $this->assertEquals('42', $env['INVRT_MAX_PAGES']);
        $this->assertEquals('http://localhost', $env['INVRT_URL']);
    }
    
    public function testSettingsSectionValuesAreOverridenByEnvironmentProfileAndDevice(): void
    {
        $this->fixture->writeConfig([
            'settings' => [
                'viewport_width' => 1920,
                'max_pages' => 42,
            ],
            'environments' => [
                'local' => ['url' => 'http://localhost'],
                'dev' => ['url' => 'http://dev.local']
            ],
            'profiles' => [
                'admin' => ['username' => 'admin_user', 'password' => 'admin_pass']
                ],
            'devices' => [
                'mobile' => ['viewport_width' => 375, 'viewport_height' => 667]
            ],
        ]);


        $env = $this->init('admin', 'desktop', 'dev');

        $this->assertEquals('http://dev.local', $env['INVRT_URL']);
        $this->assertEquals('admin_user', $env['INVRT_USERNAME']);
        $this->assertEquals('admin_pass', $env['INVRT_PASSWORD']);

        $env = $this->init('anonymous', 'mobile');

        $this->assertEquals('375', $env['INVRT_VIEWPORT_WIDTH']);
        $this->assertEquals('mobile', $env['INVRT_DEVICE']);
    }
    // ── Full precedence chain ─────────────────────────────────────────────────

    public function testFullPrecedenceChain(): void
    {
        $this->fixture->writeConfig([
            'settings'      => ['max_pages' => 10],
            'environments'  => ['local'     => ['max_pages' => 20]],
            'profiles'      => ['admin'     => ['max_pages' => 30]],
            'devices'       => ['mobile'    => ['max_pages' => 40]],
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
        $this->assertSame('https://example.com', getenv('INVRT_URL'));
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

        $this->assertEquals('1024', $env['INVRT_VIEWPORT_WIDTH']);
        $this->assertEquals('3', $env['INVRT_MAX_CRAWL_DEPTH']);
        $this->assertEquals('InVRT/1.0', $env['INVRT_USER_AGENT']);
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


    public function testInvalidYamlThrowsException(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeInvalidYamlConfig();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error reading config file');

        (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), true);
    }


    public function testMissingConfigFileThrowsException(): void
    {
        $this->fixture->setEnvironmentVariable();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not find a config.yml file');

        (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), true);
    }

    public function testMissingConfigHandledWhenNotRequired(): void
    {
        $this->fixture->setEnvironmentVariable();

        $env = (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), false);

        $this->assertIsArray($env);
        $this->assertArrayHasKey('INVRT_PROFILE', $env);
    }


    public function testInvalidYamlHandledWhenNotRequired(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeInvalidYamlConfig();

        $env = (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), false);

        $this->assertIsArray($env);
    }

    public function testMissingUrlDefaultsToEmpty(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeConfig(['name' => 'Test', 'settings' => ['max_crawl_depth' => 2]]);

        $env = (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), true);

        $this->assertEquals('', $env['INVRT_URL']);
    }

    public function testMissingEnvironmentSectionUsesDefaults(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeMinimalConfig();

        $env = (new EnvironmentService())->initialize('anonymous', 'desktop', 'nonexistent', new NullOutput(), true);

        $this->assertArrayHasKey('INVRT_ENVIRONMENT', $env);
        $this->assertEquals('nonexistent', $env['INVRT_ENVIRONMENT']);
    }

    public function testMissingProfileSectionUsesDefaults(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeMinimalConfig();

        $env = (new EnvironmentService())->initialize('nonexistent', 'desktop', 'local', new NullOutput(), true);

        $this->assertArrayHasKey('INVRT_PROFILE', $env);
        $this->assertEquals('nonexistent', $env['INVRT_PROFILE']);
    }

    public function testEmptyCredentialsInProfileYieldEmptyVars(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeConfig([
            'settings' => ['url' => 'https://example.com'],
            'profiles' => ['default' => ['username' => '', 'password' => '']],
        ]);

        $env = (new EnvironmentService())->initialize('default', 'desktop', 'local', new NullOutput(), true);

        $this->assertEquals('', $env['INVRT_USERNAME']);
        $this->assertEquals('', $env['INVRT_PASSWORD']);
    }

    public function testEnvVarsOverrideConfigCredentials(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeConfig([
            'settings' => ['url' => 'https://example.com'],
            'profiles' => ['admin' => ['username' => 'config_user', 'password' => 'config_pass']],
        ]);

        putenv('INVRT_USERNAME=env_user');
        putenv('INVRT_PASSWORD=env_pass');

        try {
            $env = (new EnvironmentService())->initialize('admin', 'desktop', 'local', new NullOutput(), true);

            $this->assertEquals('env_user', $env['INVRT_USERNAME']);
            $this->assertEquals('env_pass', $env['INVRT_PASSWORD']);
        } finally {
            putenv('INVRT_USERNAME');
            putenv('INVRT_PASSWORD');
        }
    }

    public function testValidConfigWithOnlyNameAndSettings(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeConfig([
            'name' => 'My Project',
            'settings' => ['url' => 'https://example.com', 'max_pages' => 50],
        ]);

        $env = (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), true);

        $this->assertIsArray($env);
        $this->assertNotEmpty($env);
        $this->assertEquals('https://example.com', $env['INVRT_URL']);
    }

    public function testAllDefaultEnvironmentVariablesAreSet(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeMinimalConfig();

        $env = (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), true);

        foreach (['INVRT_PROFILE', 'INVRT_DEVICE', 'INVRT_ENVIRONMENT', 'INVRT_SCRIPTS_DIR',
            'INVRT_DIRECTORY', 'INVRT_DATA_DIR', 'INVRT_COOKIES_FILE', 'INVRT_CONFIG_FILE'] as $var) {
            $this->assertArrayHasKey($var, $env, "Missing: $var");
        }
    }

    public function testSpecialCharactersInConfigValues(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeConfig([
            'settings' => [
                'url' => 'https://example.com/path?query=value&other=123',
                'user_agent' => 'Mozilla/5.0 (Test) & More "Stuff"',
            ],
        ]);

        $env = (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), true);

        $this->assertIsArray($env);
        $this->assertNotEmpty($env);
    }
}
