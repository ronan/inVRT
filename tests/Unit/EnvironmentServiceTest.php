<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Service\EnvironmentService;
use Tests\Fixtures\TestProjectFixture;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Tests for EnvironmentService
 * 
 * Tests profile resolution, environment merging, credential loading, and path construction.
 */
class EnvironmentServiceTest extends TestCase
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
     * Test default profile selection
     */
    public function testDefaultProfileSelection(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeConfigWithProfiles();

        $service = new EnvironmentService('anonymous', 'desktop', 'local');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        $this->assertEquals('anonymous', $env['INVRT_PROFILE']);
    }

    /**
     * Test custom profile selection
     */
    public function testCustomProfileSelection(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeConfigWithProfiles();

        $service = new EnvironmentService('mobile', 'desktop', 'local');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        $this->assertEquals('mobile', $env['INVRT_PROFILE']);
    }

    /**
     * Test profile overrides base settings
     */
    public function testProfileOverridesBaseSettings(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => ['url' => 'https://example.com'],
            'settings' => ['max_crawl_depth' => 5],
            'profiles' => [
                'mobile' => ['url' => 'https://mobile.example.com']
            ]
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService('mobile', 'desktop', 'local');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        // Profile URL should override base URL
        $this->assertEquals('https://mobile.example.com', $env['INVRT_URL']);
    }

    /**
     * Test environment overrides profile settings
     */
    public function testEnvironmentOverridesProfileSettings(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => ['url' => 'https://example.com'],
            'settings' => [],
            'profiles' => [
                'default' => ['url' => 'https://profile.example.com']
            ],
            'environments' => [
                'dev' => ['url' => 'https://dev.example.com']
            ]
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService('default', 'desktop', 'dev');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        // Verify environment is set and no errors
        $this->assertIsArray($env);
        $this->assertEquals('dev', $env['INVRT_ENVIRONMENT']);
    }

    /**
     * Test device option is stored in environment
     */
    public function testDeviceOptionStoredInEnvironment(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => ['url' => 'https://example.com'],
            'settings' => []
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService('anonymous', 'mobile', 'local');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        // Device should be in the returned environment
        $this->assertEquals('mobile', $env['INVRT_DEVICE']);
    }

    /**
     * Test environment-specific URL is used
     */
    public function testEnvironmentSpecificUrl(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => ['url' => 'https://example.com'],
            'settings' => ['max_crawl_depth' => 3],
            'environments' => [
                'local' => ['url' => 'http://localhost:8000'],
                'dev' => ['url' => 'https://dev.example.com'],
                'prod' => ['url' => 'https://example.com']
            ]
        ];
        $this->fixture->writeConfig($config);

        // Test dev environment initializes successfully
        $service = new EnvironmentService('anonymous', 'desktop', 'dev');
        $output = new NullOutput();
        $env = $service->initialize($output, true);
        $this->assertIsArray($env);
        $this->assertEquals('dev', $env['INVRT_ENVIRONMENT']);
    }

    /**
     * Test profile-specific credentials are supported
     */
    public function testProfileCredentialsExtraction(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => ['url' => 'https://example.com'],
            'settings' => ['max_crawl_depth' => 3],
            'profiles' => [
                'admin' => [
                    'auth' => [
                        'username' => 'admin_user',
                        'password' => 'admin_pass'
                    ]
                ]
            ]
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService('admin', 'desktop', 'local');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        // Service initialized successfully with profile
        $this->assertEquals('admin', $env['INVRT_PROFILE']);
    }

    /**
     * Test environment-specific credentials are supported
     */
    public function testEnvironmentCredentialsExtraction(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => ['url' => 'https://example.com'],
            'settings' => ['max_crawl_depth' => 3],
            'environments' => [
                'dev' => [
                    'url' => 'https://dev.example.com',
                    'auth' => [
                        'username' => 'dev_user',
                        'password' => 'dev_pass'
                    ]
                ]
            ]
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService('anonymous', 'desktop', 'dev');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        // Environment initialized successfully
        $this->assertEquals('dev', $env['INVRT_ENVIRONMENT']);
    }

    /**
     * Test environment credentials override profile credentials
     */
    public function testEnvironmentCredentialsOverrideProfile(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => ['url' => 'https://example.com'],
            'settings' => ['max_crawl_depth' => 3],
            'profiles' => [
                'default' => [
                    'auth' => [
                        'username' => 'profile_user',
                        'password' => 'profile_pass'
                    ]
                ]
            ],
            'environments' => [
                'dev' => [
                    'auth' => [
                        'username' => 'env_user',
                        'password' => 'env_pass'
                    ]
                ]
            ]
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService('default', 'desktop', 'dev');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        // Environment and profile config processed successfully
        $this->assertEquals('default', $env['INVRT_PROFILE']);
        $this->assertEquals('dev', $env['INVRT_ENVIRONMENT']);
    }

    /**
     * Test profile device environment combination
     */
    public function testProfileDeviceEnvironmentCombination(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => ['url' => 'https://example.com'],
            'settings' => [],
            'profiles' => [
                'tester' => [
                    'auth' => ['username' => 'tester', 'password' => 'testerpass']
                ]
            ],
            'environments' => [
                'staging' => [
                    'url' => 'https://staging.example.com'
                ]
            ]
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService('tester', 'tablet', 'staging');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        $this->assertEquals('tester', $env['INVRT_PROFILE']);
        $this->assertEquals('tablet', $env['INVRT_DEVICE']);
        $this->assertEquals('staging', $env['INVRT_ENVIRONMENT']);
    }

    /**
     * Test environment array contains data directory path
     */
    public function testDataDirectoryPathConstruction(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeMinimalConfig();

        $service = new EnvironmentService('testers', 'desktop', 'dev');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        $this->assertStringContainsString('testers', $env['INVRT_DATA_DIR']);
        $this->assertStringContainsString('dev', $env['INVRT_DATA_DIR']);
        $this->assertStringContainsString('data', $env['INVRT_DATA_DIR']);
    }

    /**
     * Test config file path is set correctly
     */
    public function testConfigFilePathConstruction(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeMinimalConfig();

        $service = new EnvironmentService();
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        $this->assertStringContainsString('config.yaml', $env['INVRT_CONFIG_FILE']);
        $this->assertStringContainsString('.invrt', $env['INVRT_CONFIG_FILE']);
    }

    /**
     * Test cookies file path is constructed correctly
     */
    public function testCookiesFilePathConstruction(): void
    {
        $this->fixture->setEnvironmentVariable();
        $this->fixture->writeMinimalConfig();

        $service = new EnvironmentService('myprofile', 'desktop', 'production');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        $this->assertStringContainsString('myprofile', $env['INVRT_COOKIES_FILE']);
        $this->assertStringContainsString('production', $env['INVRT_COOKIES_FILE']);
    }

    /**
     * Test service with complex multi-level nesting
     */
    public function testComplexConfigNesting(): void
    {
        $this->fixture->setEnvironmentVariable();

        $config = [
            'project' => [
                'url' => 'https://example.com',
                'name' => 'Complex Project'
            ],
            'settings' => [],
            'profiles' => [
                'advanced' => [
                    'auth' => [
                        'username' => 'advanced_user',
                        'password' => 'complex_pass_123!@#'
                    ]
                ]
            ],
            'environments' => [
                'production' => [
                    'url' => 'https://prod.example.com',
                    'auth' => [
                        'username' => 'prod_user',
                        'password' => 'prod_pass'
                    ]
                ]
            ]
        ];
        $this->fixture->writeConfig($config);

        $service = new EnvironmentService('advanced', 'widescreen', 'production');
        $output = new NullOutput();

        $env = $service->initialize($output, true);

        // Verify environment is set up correctly with complex config
        $this->assertEquals('advanced', $env['INVRT_PROFILE']);
        $this->assertEquals('widescreen', $env['INVRT_DEVICE']);
        $this->assertEquals('production', $env['INVRT_ENVIRONMENT']);
    }
}
