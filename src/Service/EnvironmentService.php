<?php

namespace App\Service;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class EnvironmentService
{
    private string $profile;
    private string $device;
    private string $environment;
    private string $scriptsDir;
    private string $invrtDirectory;
    private array $config = [];
    private array $resolved = ConfigDefinition::DEFAULTS;

    public function __construct(
        string $profile = 'anonymous',
        string $device = 'desktop',
        string $environment = 'local',
    ) {
        $this->profile = $profile;
        $this->device = $device;
        $this->environment = $environment;
        $this->scriptsDir = __DIR__ . '/..';
    }

    /**
     * Initialize environment variables and load configuration.
     *
     * @throws \RuntimeException when $requireConfig is true and file is missing/invalid
     */
    public function initialize(OutputInterface $output, bool $requireConfig = true): array
    {
        $this->setupDirectories();

        if ($requireConfig) {
            $this->config = $this->loadConfig($output);
        } else {
            $this->config = $this->tryLoadConfig();
        }

        $this->resolveConfig();

        return $this->getEnvironmentArray();
    }

    private function setupDirectories(): void
    {
        $this->invrtDirectory = getenv('INVRT_DIRECTORY') ?: $this->joinPath(
            getenv('INIT_CWD') ?: (string) getcwd(),
            '.invrt',
        );

        putenv('INVRT_DIRECTORY=' . $this->invrtDirectory);
        putenv('INVRT_SCRIPTS_DIR=' . $this->scriptsDir);
        putenv('INVRT_PROFILE=' . $this->profile);
        putenv('INVRT_DEVICE=' . $this->device);
        putenv('INVRT_ENVIRONMENT=' . $this->environment);
    }

    /** Load and validate config; throw on missing file or parse error. */
    private function loadConfig(OutputInterface $output): array
    {
        $configFile = $this->joinPath($this->invrtDirectory, 'config.yaml');

        if (!file_exists($configFile)) {
            throw new \RuntimeException(
                "Configuration file not found at $configFile. Please run 'invrt init' to initialize the project.",
            );
        }

        $output->writeln(
            "<comment>#  Loading project settings for profile: {$this->profile} "
            . "device: {$this->device} "
            . "environment: {$this->environment}</comment>",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        try {
            $raw = Yaml::parse((string) file_get_contents($configFile)) ?: [];
            // Validate structure — throws on unknown keys or invalid types
            (new Processor())->processConfiguration(new ConfigDefinition(), [$raw]);
            return $raw;
        } catch (\Exception $e) {
            throw new \RuntimeException('Error reading config file: ' . $e->getMessage());
        }
    }

    /** Attempt to load config silently; return empty array on any failure. */
    private function tryLoadConfig(): array
    {
        $configFile = $this->joinPath($this->invrtDirectory, 'config.yaml');

        if (!file_exists($configFile)) {
            return [];
        }

        try {
            $raw = Yaml::parse((string) file_get_contents($configFile)) ?: [];
            (new Processor())->processConfiguration(new ConfigDefinition(), [$raw]);
            return $raw;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Merge settings → environment → profile → device, then export env vars.
     * Uses raw YAML array so only explicitly set keys override earlier values.
     */
    private function resolveConfig(): void
    {
        $this->resolved = ConfigDefinition::DEFAULTS;

        $sections = [
            $this->config['settings'] ?? [],
            $this->config['environments'][$this->environment] ?? [],
            $this->config['profiles'][$this->profile] ?? [],
            $this->config['devices'][$this->device] ?? [],
        ];

        foreach ($sections as $section) {
            foreach (ConfigDefinition::CONFIG_KEYS as $key) {
                if (array_key_exists($key, $section)) {
                    $this->resolved[$key] = $section[$key];
                }
            }
        }

        // Env var overrides for credentials (highest precedence)
        foreach (['username', 'password'] as $cred) {
            $envVal = getenv('INVRT_' . strtoupper($cred));
            if ($envVal !== false && $envVal !== '') {
                $this->resolved[$cred] = $envVal;
            }
        }

        // Export all resolved values for subprocess access
        foreach ($this->resolved as $key => $value) {
            putenv('INVRT_' . strtoupper($key) . '=' . $value);
        }
    }

    /**
     * Return all environment variables as an array for passing to shell scripts.
     */
    public function getEnvironmentArray(): array
    {
        $dataDir = $this->joinPath($this->invrtDirectory, 'data', $this->profile, $this->environment);

        return [
            'INVRT_PROFILE'                 => $this->profile,
            'INVRT_DEVICE'                  => $this->device,
            'INVRT_ENVIRONMENT'             => $this->environment,
            'INVRT_SCRIPTS_DIR'             => $this->scriptsDir,
            'INVRT_DIRECTORY'               => $this->invrtDirectory,
            'INVRT_DATA_DIR'                => $dataDir,
            'INVRT_COOKIES_FILE'            => $this->joinPath($dataDir, 'cookies'),
            'INVRT_CONFIG_FILE'             => $this->joinPath($this->invrtDirectory, 'config.yaml'),
            'INVRT_URL'                     => (string) $this->resolved['url'],
            'INVRT_LOGIN_URL'               => (string) $this->resolved['login_url'],
            'INVRT_USERNAME'                => (string) $this->resolved['username'],
            'INVRT_PASSWORD'                => (string) $this->resolved['password'],
            'INVRT_VIEWPORT_WIDTH'          => (string) $this->resolved['viewport_width'],
            'INVRT_VIEWPORT_HEIGHT'         => (string) $this->resolved['viewport_height'],
            'INVRT_MAX_CRAWL_DEPTH'         => (string) $this->resolved['max_crawl_depth'],
            'INVRT_MAX_PAGES'               => (string) $this->resolved['max_pages'],
            'INVRT_USER_AGENT'              => (string) $this->resolved['user_agent'],
            'INVRT_MAX_CONCURRENT_REQUESTS' => (string) $this->resolved['max_concurrent_requests'],
        ];
    }

    public function getEnv(string $key): string
    {
        return $this->getEnvironmentArray()[$key] ?? '';
    }

    private function joinPath(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
