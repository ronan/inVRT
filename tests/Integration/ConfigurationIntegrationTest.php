<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Integration tests using fixture files
 */
class ConfigurationIntegrationTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/../../tests/fixtures';
    }

    /**
     * Test loading full config fixture file
     */
    public function testLoadFullConfigFixture(): void
    {
        $configFile = $this->fixturesDir . '/config.yaml';
        $this->assertFileExists($configFile);

        $contents = file_get_contents($configFile);
        $config = Yaml::parse($contents);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('project', $config);
        $this->assertArrayHasKey('settings', $config);
        $this->assertArrayHasKey('profiles', $config);
        $this->assertArrayHasKey('environments', $config);
    }

    /**
     * Test loading minimal config fixture file
     */
    public function testLoadMinimalConfigFixture(): void
    {
        $configFile = $this->fixturesDir . '/config-minimal.yaml';
        $this->assertFileExists($configFile);

        $contents = file_get_contents($configFile);
        $config = Yaml::parse($contents);

        $this->assertIsArray($config);
        $this->assertEquals('https://example.com', $config['project']['url']);
        $this->assertEquals(2, $config['settings']['max_crawl_depth']);
    }

    /**
     * Test loading cookies fixture file
     */
    public function testLoadCookiesFixture(): void
    {
        $cookiesFile = $this->fixturesDir . '/cookies.json';
        $this->assertFileExists($cookiesFile);

        $contents = file_get_contents($cookiesFile);
        $cookies = json_decode($contents, true);

        $this->assertIsArray($cookies);
        $this->assertCount(3, $cookies);

        // Validate first cookie
        $this->assertEquals('session_id', $cookies[0]['name']);
        $this->assertEquals('abc123xyz789', $cookies[0]['value']);
        $this->assertTrue($cookies[0]['secure']);
    }

    /**
     * Test config value extraction using getConfig
     */
    public function testConfigValueExtraction(): void
    {
        require_once __DIR__ . '/../../src/invrt-utils.inc.php';

        $configFile = $this->fixturesDir . '/config.yaml';
        $contents = file_get_contents($configFile);
        $config = Yaml::parse($contents);

        // Test simple value extraction
        $url = getConfig($config, 'project.url');
        $this->assertEquals('https://example.com', $url);

        // Test nested value extraction
        $maxDepth = getConfig($config, 'settings.max_crawl_depth');
        $this->assertEquals(5, $maxDepth);

        // Test profile extraction
        $profileUrl = getConfig($config, 'profiles.default.url');
        $this->assertEquals('https://example.com', $profileUrl);

        // Test environment extraction
        $devUrl = getConfig($config, 'environments.dev.url');
        $this->assertEquals('https://dev.example.com', $devUrl);
    }

    /**
     * Test profile override behavior
     */
    public function testProfileOverrideBehavior(): void
    {
        require_once __DIR__ . '/../../src/invrt-utils.inc.php';

        $configFile = $this->fixturesDir . '/config.yaml';
        $contents = file_get_contents($configFile);
        $config = Yaml::parse($contents);

        // Get base setting
        $baseMaxDepth = getConfig($config, 'settings.max_crawl_depth');
        
        // Get profile override
        $profileMaxDepth = getConfig($config, 'profiles.default.max_crawl_depth');
        
        // Profile should override base setting
        $this->assertEquals(5, $baseMaxDepth);
        $this->assertEquals(3, $profileMaxDepth);
        $this->assertNotEquals($baseMaxDepth, $profileMaxDepth);
    }

    /**
     * Test environment override behavior
     */
    public function testEnvironmentOverrideBehavior(): void
    {
        require_once __DIR__ . '/../../src/invrt-utils.inc.php';

        $configFile = $this->fixturesDir . '/config.yaml';
        $contents = file_get_contents($configFile);
        $config = Yaml::parse($contents);

        // Get base URL
        $baseUrl = getConfig($config, 'project.url');
        
        // Get dev environment URL
        $devUrl = getConfig($config, 'environments.dev.url');
        
        // Get prod environment URL
        $prodUrl = getConfig($config, 'environments.prod.url');

        // Different environments should have different URLs
        $this->assertEquals('https://example.com', $baseUrl);
        $this->assertEquals('https://dev.example.com', $devUrl);
        $this->assertEquals('https://example.com', $prodUrl);
    }

    /**
     * Test auth credentials extraction from profiles
     */
    public function testAuthCredentialsExtractionFromProfiles(): void
    {
        require_once __DIR__ . '/../../src/invrt-utils.inc.php';

        $configFile = $this->fixturesDir . '/config.yaml';
        $contents = file_get_contents($configFile);
        $config = Yaml::parse($contents);

        // Extract auth from default profile
        $username = getConfig($config, 'profiles.default.auth.username');
        $password = getConfig($config, 'profiles.default.auth.password');

        $this->assertEquals('default_user', $username);
        $this->assertEquals('default_pass', $password);
    }

    /**
     * Test auth credentials extraction from environments
     */
    public function testAuthCredentialsExtractionFromEnvironments(): void
    {
        require_once __DIR__ . '/../../src/invrt-utils.inc.php';

        $configFile = $this->fixturesDir . '/config.yaml';
        $contents = file_get_contents($configFile);
        $config = Yaml::parse($contents);

        // Extract auth from dev environment
        $devUsername = getConfig($config, 'environments.dev.auth.username');
        $devPassword = getConfig($config, 'environments.dev.auth.password', '');

        $this->assertEquals('dev_user', $devUsername);
        $this->assertEquals('dev_pass', $devPassword); // Password is defined in dev environment
    }

    /**
     * Test device-specific profiles
     */
    public function testDeviceSpecificProfiles(): void
    {
        require_once __DIR__ . '/../../src/invrt-utils.inc.php';

        $configFile = $this->fixturesDir . '/config.yaml';
        $contents = file_get_contents($configFile);
        $config = Yaml::parse($contents);

        // Desktop profile
        $desktopDepth = getConfig($config, 'profiles.default.max_crawl_depth');
        
        // Mobile profile
        $mobileDepth = getConfig($config, 'profiles.mobile.max_crawl_depth');

        $this->assertEquals(3, $desktopDepth);
        $this->assertEquals(2, $mobileDepth);
        $this->assertLessThan($desktopDepth, $mobileDepth);
    }

    /**
     * Test missing optional setting returns default
     */
    public function testMissingOptionalSettingReturnsDefault(): void
    {
        require_once __DIR__ . '/../../src/invrt-utils.inc.php';

        $configFile = $this->fixturesDir . '/config.yaml';
        $contents = file_get_contents($configFile);
        $config = Yaml::parse($contents);

        // Mobile profile doesn't have max_pages setting
        $maxPages = getConfig($config, 'profiles.mobile.max_pages', 100);

        $this->assertEquals(100, $maxPages);
    }

    /**
     * Test complete scenario: load config, extract values, process for execution
     */
    public function testCompleteConfigurationScenario(): void
    {
        require_once __DIR__ . '/../../src/invrt-utils.inc.php';

        $configFile = $this->fixturesDir . '/config.yaml';
        $contents = file_get_contents($configFile);
        $config = Yaml::parse($contents);

        // Simulate profile and environment selection
        $profile = 'default';
        $environment = 'dev';

        // Build environment variables for execution
        $env = [];
        
        // Base settings
        $env['INVRT_URL'] = getConfig($config, 'project.url', '');
        $env['INVRT_DEPTH_TO_CRAWL'] = getConfig($config, 'settings.max_crawl_depth', '');
        
        // Profile overrides
        if (isset($config['profiles'][$profile])) {
            if (isset($config['profiles'][$profile]['url'])) {
                $env['INVRT_URL'] = $config['profiles'][$profile]['url'];
            }
            if (isset($config['profiles'][$profile]['max_crawl_depth'])) {
                $env['INVRT_DEPTH_TO_CRAWL'] = $config['profiles'][$profile]['max_crawl_depth'];
            }
        }

        // Environment overrides
        if (isset($config['environments'][$environment])) {
            if (isset($config['environments'][$environment]['url'])) {
                $env['INVRT_URL'] = $config['environments'][$environment]['url'];
            }
        }

        // Verify final values
        $this->assertEquals('https://dev.example.com', $env['INVRT_URL']);
        $this->assertEquals(3, $env['INVRT_DEPTH_TO_CRAWL']);
        $this->assertArrayHasKey('INVRT_URL', $env);
        $this->assertArrayHasKey('INVRT_DEPTH_TO_CRAWL', $env);
    }
}
?>
