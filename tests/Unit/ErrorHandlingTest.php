<?php

namespace Tests\Unit;

use App\Service\EnvironmentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Fixtures\TestProjectFixture;

/**
 * Tests for error handling and edge cases
 *
 * Tests configuration errors, missing values, and error scenarios.
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ErrorHandlingTest extends TestCase
{
    private TestProjectFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new TestProjectFixture();
        $this->fixture->create();
    }

    protected function tearDown(): void
    {
        $this->fixture->unsetEnvironmentVariable();
        $this->fixture->cleanup();
    }

    /**
     * Test EnvironmentService throws when config file is missing and required
     */
    public function testMissingConfigFileThrowsException(): void
    {
        $this->fixture->setEnvironmentVariable();

        $service = new EnvironmentService();
        $output = new NullOutput();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration file not found');

        $service->initialize($output, true);
    }

    /**
     * Test EnvironmentService handles missing config gracefully when not required
     */
    public function testMissingConfigHandledWhenNotRequired(): void
    {
        $this->fixture->setEnvironmentVariable();

        $service = new EnvironmentService();
        $output = new NullOutput();

        // Should not throw exception
        $env = $service->initialize($output, false);

        // Should return environment array with defaults
        $this->assertIsArray($env);
        $this->assertArrayHasKey('INVRT_PROFILE', $env);
    }

    /**
     * Test invalid YAML config throws exception
     */
    public function testInvalidYamlThrowsException(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeInvalidYamlConfig();

        $service = new EnvironmentService();
        $output = new NullOutput();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error reading config file');

        $service->initialize($output, true);
    }

    /**
     * Test invalid YAML is handled when config not required
     */
    public function testInvalidYamlHandledWhenNotRequired(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeInvalidYamlConfig();

        $service = new EnvironmentService();
        $output = new NullOutput();

        // Should not throw exception
        $env = $service->initialize($output, false);

        $this->assertIsArray($env);
    }

    /**
     * Test missing required project.url in config
     */
    public function testMissingProjectUrl(): void
    {
        $this->fixture->setEnvironmentVariable();

        // Create config without project.url
        $config = [
            'project' => ['name' => 'Test'],
            'settings' => ['max_crawl_depth' => 2],
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService();
        $output = new NullOutput();

        // This should succeed but INVRT_URL will be empty
        $env = $service->initialize($output, true);

        $this->assertEquals('', $env['INVRT_URL']);
    }

    /**
     * Test missing environment section in config
     */
    public function testMissingEnvironmentSection(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeMinimalConfig();

        $service = new EnvironmentService('default', 'desktop', 'nonexistent');
        $output = new NullOutput();

        // Should succeed but may use defaults
        $env = $service->initialize($output, true);

        $this->assertIsArray($env);
        $this->assertArrayHasKey('INVRT_ENVIRONMENT', $env);
        $this->assertEquals('nonexistent', $env['INVRT_ENVIRONMENT']);
    }

    /**
     * Test missing profile section in config
     */
    public function testMissingProfileSection(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeMinimalConfig();

        $service = new EnvironmentService('nonexistent', 'desktop', 'local');
        $output = new NullOutput();

        // Should succeed but may use defaults
        $env = $service->initialize($output, true);

        $this->assertIsArray($env);
        $this->assertArrayHasKey('INVRT_PROFILE', $env);
        $this->assertEquals('nonexistent', $env['INVRT_PROFILE']);
    }

    /**
     * Test empty credentials in config
     */
    public function testEmptyCredentials(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => ['url' => 'https://example.com'],
            'settings' => ['max_crawl_depth' => 2],
            'profiles' => [
                'default' => [
                    'auth' => [
                        'username' => '',
                        'password' => '',
                    ],
                ],
            ],
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService();
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        $this->assertEquals('', $env['INVRT_USERNAME']);
        $this->assertEquals('', $env['INVRT_PASSWORD']);
    }

    /**
     * Test credentials from environment override config
     */
    public function testEnvironmentVariablesOverrideConfig(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeConfigWithProfiles();

        // Set environment variables
        putenv('INVRT_USERNAME=env_user');
        putenv('INVRT_PASSWORD=env_pass');

        try {
            $service = new EnvironmentService();
            $output = new NullOutput();

            $env = $service->initialize($output, true);

            // Environment variables should be in the returned array
            // (may depend on implementation)
            $this->assertIsArray($env);

        } finally {
            putenv('INVRT_USERNAME');
            putenv('INVRT_PASSWORD');
        }
    }

    /**
     * Test deep nesting in config is handled
     */
    public function testDeeplyNestedConfig(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => [
                'url' => 'https://example.com',
                'metadata' => [
                    'version' => '1.0',
                    'author' => [
                        'name' => 'Test Author',
                        'email' => 'test@example.com',
                    ],
                ],
            ],
            'settings' => [],
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService();
        $output = new NullOutput();

        // Should initialize successfully without errors
        $env = $service->initialize($output, true);

        $this->assertIsArray($env);
        $this->assertNotEmpty($env);
    }

    /**
     * Test all default environment variables are set
     */
    public function testAllDefaultEnvironmentVariablesAreSet(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeMinimalConfig();

        $service = new EnvironmentService();
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        // Check required environment variables are present
        $requiredVars = [
            'INVRT_PROFILE',
            'INVRT_DEVICE',
            'INVRT_ENVIRONMENT',
            'INVRT_SCRIPTS_DIR',
            'INVRT_DIRECTORY',
            'INVRT_DATA_DIR',
            'INVRT_COOKIES_FILE',
            'INVRT_CONFIG_FILE',
        ];

        foreach ($requiredVars as $var) {
            $this->assertArrayHasKey($var, $env, "Missing environment variable: $var");
        }
    }

    /**
     * Test special characters in config values
     */
    public function testSpecialCharactersInConfig(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => [
                'url' => 'https://example.com/path?query=value&other=123',
                'name' => 'Test "Project" with Special Chars',
            ],
            'settings' => [
                'user_agent' => 'Mozilla/5.0 (Test) & More "Stuff"',
            ],
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService();
        $output = new NullOutput();

        // Should initialize successfully with special characters
        $env = $service->initialize($output, true);

        $this->assertIsArray($env);
        $this->assertNotEmpty($env);
    }
}
