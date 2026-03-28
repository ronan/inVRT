<?php

namespace Tests\Fixtures;

use Symfony\Component\Yaml\Yaml;

/**
 * TestProjectFixture - Manages temporary test project directories and configuration
 * 
 * Provides helpers for creating realistic test project structures with fixture config files.
 * Automatically cleans up temporary directories after tests.
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class TestProjectFixture
{
    private string $tempDir;
    private string $projectDir;
    private string $invrtDir;

    public function __construct(?string $baseDir = null)
    {
        $base = $baseDir ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'invrt_test_' . uniqid();
        $this->tempDir = $base;
        $this->projectDir = $base . DIRECTORY_SEPARATOR . 'project';
        $this->invrtDir = $base . DIRECTORY_SEPARATOR . 'project' . DIRECTORY_SEPARATOR . '.invrt';
    }

    /**
     * Create the project directory structure
     */
    public function create(): self
    {
        @mkdir($this->projectDir, 0755, true);
        @mkdir($this->invrtDir, 0755, true);
        @mkdir($this->invrtDir . '/data', 0755, true);
        return $this;
    }

    /**
     * Clean up temporary directories
     */
    public function cleanup(): void
    {
        if (is_dir($this->tempDir)) {
            $this->rmdirRecursive($this->tempDir);
        }
    }

    /**
     * Recursively remove directory and contents
     */
    private function rmdirRecursive(string $dir): bool
    {
        if (!is_dir($dir)) {
            return @unlink($dir);
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $this->rmdirRecursive("$dir/$file");
        }

        return @rmdir($dir);
    }

    /**
     * Get the project root directory
     */
    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    /**
     * Get the .invrt directory
     */
    public function getInvrtDir(): string
    {
        return $this->invrtDir;
    }

    /**
     * Get the temporary base directory
     */
    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    /**
     * Write a config.yaml file with minimal configuration
     */
    public function writeMinimalConfig(): self
    {
        $config = [
            'settings' => [
                'url' => 'https://example.com',
                'max_crawl_depth' => 2,
                'max_pages' => 50,
                'user_agent' => 'Mozilla/5.0 (Test)',
            ],
        ];

        return $this->writeConfig($config);
    }

    /**
     * Write a config.yaml file with profiles
     */
    public function writeConfigWithProfiles(): self
    {
        $config = [
            'settings' => [
                'url' => 'https://example.com',
                'max_crawl_depth' => 3,
                'max_pages' => 100,
                'user_agent' => 'Mozilla/5.0',
            ],
            'profiles' => [
                'default' => [
                    'url' => 'https://example.com',
                    'max_crawl_depth' => 2,
                    'username' => 'testuser',
                    'password' => 'testpass',
                ],
                'mobile' => [
                    'url' => 'https://mobile.example.com',
                    'max_crawl_depth' => 1,
                ],
            ],
        ];

        return $this->writeConfig($config);
    }

    /**
     * Write a config.yaml file with environments
     */
    public function writeConfigWithEnvironments(): self
    {
        $config = [
            'settings' => [
                'url' => 'https://example.com',
                'max_crawl_depth' => 3,
                'max_pages' => 100,
            ],
            'environments' => [
                'local' => [
                    'url' => 'http://localhost:8000',
                ],
                'dev' => [
                    'url' => 'https://dev.example.com',
                    'username' => 'dev_user',
                    'password' => 'dev_pass',
                ],
                'staging' => [
                    'url' => 'https://staging.example.com',
                ],
                'prod' => [
                    'url' => 'https://example.com',
                ],
            ],
        ];

        return $this->writeConfig($config);
    }

    /**
     * Write a config.yaml file with devices
     */
    public function writeConfigWithDevices(): self
    {
        $config = [
            'settings' => [
                'url' => 'https://example.com',
                'max_crawl_depth' => 3,
                'max_pages' => 100,
                'viewport_width' => 1920,
                'viewport_height' => 1080,
            ],
            'devices' => [
                'desktop' => [
                    'viewport_width' => 1920,
                    'viewport_height' => 1080,
                ],
                'mobile' => [
                    'viewport_width' => 375,
                    'viewport_height' => 667,
                ],
                'tablet' => [
                    'viewport_width' => 768,
                    'viewport_height' => 1024,
                ],
            ],
        ];

        return $this->writeConfig($config);
    }

    /**
     * Write a custom config.yaml file
     */
    public function writeConfig(array $config): self
    {
        $this->create();
        $configPath = $this->invrtDir . '/config.yaml';
        $yaml = Yaml::dump($config, 4, 2);
        file_put_contents($configPath, $yaml);
        return $this;
    }

    /**
     * Write an invalid YAML config file
     */
    public function writeInvalidYamlConfig(): self
    {
        $this->create();
        $configPath = $this->invrtDir . '/config.yaml';
        file_put_contents($configPath, "invalid: yaml: content: [");
        return $this;
    }

    /**
     * Create data directory structure for a profile/environment
     */
    public function createDataDir(string $profile, string $environment): string
    {
        $dataDir = $this->invrtDir . "/data/$profile/$environment";
        @mkdir($dataDir, 0755, true);
        return $dataDir;
    }

    /**
     * Write a cookies.json file
     */
    public function writeCookiesFile(string $profile, string $environment): string
    {
        $dataDir = $this->createDataDir($profile, $environment);
        $cookiesPath = $dataDir . '/cookies.json';
        
        $cookies = [
            [
                'name' => 'session_id',
                'value' => 'test_session_123',
                'domain' => 'example.com',
                'path' => '/',
                'secure' => true
            ],
            [
                'name' => 'user_id',
                'value' => 'user_456',
                'domain' => 'example.com',
                'path' => '/',
                'secure' => false
            ]
        ];

        file_put_contents($cookiesPath, json_encode($cookies, JSON_PRETTY_PRINT));
        return $cookiesPath;
    }

    /**
     * Write a crawled_urls.txt file with one URL path per line
     *
     * @param string[] $urls URL paths (e.g. ['/', '/about.html'])
     */
    public function writeCrawledUrlsFile(string $profile, string $environment, array $urls): self
    {
        $dataDir = $this->createDataDir($profile, $environment);
        file_put_contents($dataDir . '/crawled_urls.txt', implode("\n", $urls));
        return $this;
    }

    /**
     * Check if config file exists
     */
    public function hasConfig(): bool
    {
        return file_exists($this->invrtDir . '/config.yaml');
    }

    /**
     * Get config file path
     */
    public function getConfigPath(): string
    {
        return $this->invrtDir . '/config.yaml';
    }

    /**
     * Read config file as array
     */
    public function readConfig(): array
    {
        $configPath = $this->getConfigPath();
        if (!file_exists($configPath)) {
            return [];
        }

        try {
            $contents = file_get_contents($configPath);
            return Yaml::parse($contents) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Set environment variable for INVRT_DIRECTORY
     */
    public function setEnvironmentVariable(): self
    {
        putenv('INVRT_DIRECTORY=' . $this->invrtDir);
        return $this;
    }

    /**
     * Unset INVRT_DIRECTORY environment variable
     */
    public function unsetEnvironmentVariable(): self
    {
        putenv('INVRT_DIRECTORY=');
        return $this;
    }
}
