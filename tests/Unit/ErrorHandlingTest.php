<?php

namespace Tests\Unit;

use App\Service\EnvironmentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Fixtures\TestProjectFixture;

/**
 * Tests for error handling and edge cases in EnvironmentService.
 */
class ErrorHandlingTest extends TestCase
{
    private TestProjectFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new TestProjectFixture();
        $this->fixture->create();
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

    public function testMissingConfigFileThrowsException(): void
    {
        $this->fixture->setEnvironmentVariable();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration file not found');

        (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), true);
    }

    public function testMissingConfigHandledWhenNotRequired(): void
    {
        $this->fixture->setEnvironmentVariable();

        $env = (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), false);

        $this->assertIsArray($env);
        $this->assertArrayHasKey('INVRT_PROFILE', $env);
    }

    public function testInvalidYamlThrowsException(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeInvalidYamlConfig();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error reading config file');

        (new EnvironmentService())->initialize('anonymous', 'desktop', 'local', new NullOutput(), true);
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
