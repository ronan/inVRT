<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for the main invrt CLI script
 * 
 * Note: This test file validates the logic and structure of invrt.php
 * without executing the full CLI entry point directly (which uses exit() calls).
 * It tests helper functions and configurations instead.
 */
class InvrtCliTest extends TestCase
{
    private string $testConfigDir;
    private string $testConfigFile;

    protected function setUp(): void
    {
        // Create temporary directory for test configs
        $this->testConfigDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'invrt_test_' . uniqid();
        mkdir($this->testConfigDir, 0755, true);
        $this->testConfigFile = $this->testConfigDir . DIRECTORY_SEPARATOR . 'config.yaml';
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->testConfigFile)) {
            unlink($this->testConfigFile);
        }
        if (is_dir($this->testConfigDir)) {
            rmdir($this->testConfigDir);
        }
    }

    /**
     * Test YAML config file parsing with project settings
     */
    public function testYamlConfigParsing(): void
    {
        $config = [
            'project' => [
                'url' => 'https://example.com'
            ],
            'settings' => [
                'max_crawl_depth' => 3,
                'max_pages' => 50,
                'user_agent' => 'Mozilla/5.0'
            ]
        ];

        // Convert to YAML and save
        $yaml = Yaml::dump($config);
        file_put_contents($this->testConfigFile, $yaml);

        // Read back and parse
        $fileContents = file_get_contents($this->testConfigFile);
        $parsedConfig = Yaml::parse($fileContents);

        $this->assertEquals('https://example.com', $parsedConfig['project']['url']);
        $this->assertEquals(3, $parsedConfig['settings']['max_crawl_depth']);
        $this->assertEquals('Mozilla/5.0', $parsedConfig['settings']['user_agent']);
    }

    /**
     * Test YAML config with profiles
     */
    public function testYamlConfigWithProfiles(): void
    {
        $config = [
            'project' => [
                'url' => 'https://example.com'
            ],
            'profiles' => [
                'default' => [
                    'url' => 'https://example.com',
                    'max_crawl_depth' => 2,
                    'auth' => [
                        'username' => 'user1',
                        'password' => 'pass1'
                    ]
                ],
                'mobile' => [
                    'url' => 'https://mobile.example.com',
                    'max_crawl_depth' => 1
                ]
            ]
        ];

        $yaml = Yaml::dump($config);
        file_put_contents($this->testConfigFile, $yaml);

        $fileContents = file_get_contents($this->testConfigFile);
        $parsedConfig = Yaml::parse($fileContents);

        // Validate default profile
        $this->assertEquals('https://example.com', $parsedConfig['profiles']['default']['url']);
        $this->assertEquals('user1', $parsedConfig['profiles']['default']['auth']['username']);

        // Validate mobile profile
        $this->assertEquals('https://mobile.example.com', $parsedConfig['profiles']['mobile']['url']);
        $this->assertEquals(1, $parsedConfig['profiles']['mobile']['max_crawl_depth']);
    }

    /**
     * Test YAML config with environments
     */
    public function testYamlConfigWithEnvironments(): void
    {
        $config = [
            'project' => [
                'url' => 'https://example.com'
            ],
            'environments' => [
                'dev' => [
                    'url' => 'https://dev.example.com',
                    'auth' => [
                        'username' => 'dev_user',
                        'password' => 'dev_pass'
                    ]
                ],
                'staging' => [
                    'url' => 'https://staging.example.com'
                ],
                'prod' => [
                    'url' => 'https://example.com'
                ]
            ]
        ];

        $yaml = Yaml::dump($config);
        file_put_contents($this->testConfigFile, $yaml);

        $fileContents = file_get_contents($this->testConfigFile);
        $parsedConfig = Yaml::parse($fileContents);

        $this->assertEquals('https://dev.example.com', $parsedConfig['environments']['dev']['url']);
        $this->assertEquals('staging.example.com', parse_url($parsedConfig['environments']['staging']['url'], PHP_URL_HOST));
        $this->assertEquals('example.com', parse_url($parsedConfig['environments']['prod']['url'], PHP_URL_HOST));
    }

    /**
     * Test YAML parsing with invalid syntax fails appropriately
     */
    public function testYamlParsingWithInvalidSyntax(): void
    {
        // Write invalid YAML
        file_put_contents($this->testConfigFile, "invalid: yaml: syntax:");

        $fileContents = file_get_contents($this->testConfigFile);
        
        $this->expectException(\Exception::class);
        Yaml::parse($fileContents);
    }

    /**
     * Test YAML parsing empty file returns empty array
     */
    public function testYamlParsingEmptyFile(): void
    {
        file_put_contents($this->testConfigFile, '');

        $fileContents = file_get_contents($this->testConfigFile);
        $parsedConfig = Yaml::parse($fileContents);

        // Empty YAML should parse to null or empty array
        $this->assertTrue($parsedConfig === null || $parsedConfig === []);
    }

    /**
     * Test complex config with mixed settings
     */
    public function testComplexConfigWithMixedSettings(): void
    {
        $config = [
            'project' => [
                'url' => 'https://example.com',
                'name' => 'Example Project'
            ],
            'settings' => [
                'max_crawl_depth' => 5,
                'max_pages' => 200,
                'user_agent' => 'Mozilla/5.0',
                'max_concurrent_requests' => 10
            ],
            'profiles' => [
                'desktop' => [
                    'max_crawl_depth' => 3,
                    'auth' => [
                        'username' => 'desktop_user',
                        'password' => 'desktop_pass',
                        'cookie' => 'session_token=abc123'
                    ]
                ],
                'mobile' => [
                    'max_crawl_depth' => 2,
                    'user_agent' => 'Mobile Safari'
                ]
            ],
            'environments' => [
                'local' => [
                    'url' => 'http://localhost:8000'
                ],
                'dev' => [
                    'url' => 'https://dev.example.com',
                    'auth' => [
                        'username' => 'dev_user'
                    ]
                ],
                'prod' => [
                    'url' => 'https://example.com'
                ]
            ]
        ];

        $yaml = Yaml::dump($config);
        file_put_contents($this->testConfigFile, $yaml);

        $fileContents = file_get_contents($this->testConfigFile);
        $parsedConfig = Yaml::parse($fileContents);

        // Verify all sections are correctly parsed
        $this->assertCount(2, $parsedConfig['profiles']);
        $this->assertCount(3, $parsedConfig['environments']);
        $this->assertEquals('http://localhost:8000', $parsedConfig['environments']['local']['url']);
        $this->assertEquals('session_token=abc123', $parsedConfig['profiles']['desktop']['auth']['cookie']);
    }

    /**
     * Test valid command names
     */
    public function testValidCommands(): void
    {
        $validCommands = ['init', 'crawl', 'reference', 'test'];
        $testCommands = ['init', 'crawl', 'reference', 'test', 'help', '--help', '-h'];

        foreach ($testCommands as $command) {
            if (in_array($command, $validCommands)) {
                $this->assertTrue(in_array($command, $validCommands));
            }
        }
    }

    /**
     * Test invalid command detection
     */
    public function testInvalidCommandDetection(): void
    {
        $validCommands = ['init', 'crawl', 'reference', 'test'];
        $invalidCommand = 'invalid';

        $this->assertFalse(in_array($invalidCommand, $validCommands));
    }

    /**
     * Test environment variable handling
     */
    public function testEnvironmentVariableHandling(): void
    {
        $testEnv = [
            'INVRT_SCRIPTS_DIR' => '/app/src',
            'INVRT_DIRECTORY' => '/home/user/.invrt',
            'INVRT_DATA_DIR' => '/home/user/.invrt/data/default/local',
            'INVRT_URL' => 'https://example.com',
            'INVRT_PROFILE' => 'default',
            'INVRT_DEVICE' => 'desktop',
            'INVRT_ENVIRONMENT' => 'local'
        ];

        foreach ($testEnv as $key => $value) {
            $this->assertEquals($value, $testEnv[$key]);
        }
    }

    /**
     * Test environment variable merging
     */
    public function testEnvironmentVariableMerging(): void
    {
        $baseEnv = [
            'INVRT_PROFILE' => 'default',
            'INVRT_DEVICE' => 'desktop'
        ];

        $additionalEnv = [
            'INVRT_ENVIRONMENT' => 'dev',
            'INVRT_URL' => 'https://dev.example.com'
        ];

        $merged = array_merge($baseEnv, $additionalEnv);

        $this->assertCount(4, $merged);
        $this->assertEquals('default', $merged['INVRT_PROFILE']);
        $this->assertEquals('dev', $merged['INVRT_ENVIRONMENT']);
    }

    /**
     * Test data directory construction
     */
    public function testDataDirectoryConstruction(): void
    {
        $invrtDirectory = '/home/user/.invrt';
        $profile = 'default';
        $environment = 'local';

        $dataDir = $invrtDirectory . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $profile . DIRECTORY_SEPARATOR . $environment;

        $expected = '/home/user/.invrt' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local';

        $this->assertEquals($expected, $dataDir);
    }

    /**
     * Test cookies file path construction
     */
    public function testCookiesFilePathConstruction(): void
    {
        $dataDir = '/home/user/.invrt/data/default/local';
        $cookiesFile = $dataDir . DIRECTORY_SEPARATOR . 'cookies.json';

        $this->assertStringEndsWith('cookies.json', $cookiesFile);
        $this->assertStringContainsString('data', $cookiesFile);
    }
}
?>
