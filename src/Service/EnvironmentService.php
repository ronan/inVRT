<?php

namespace App\Service;

use App\Input\InvrtConfiguration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

class EnvironmentService
{
    private readonly string $scriptsDir;

    public function __construct()
    {
        $this->scriptsDir = __DIR__ . '/..';
    }

    /**
     * Initialise environment, load + resolve config, export env vars for subprocesses.
     *
     * @throws \RuntimeException when $requireConfig is true and file is missing/invalid
     */
    public function initialize(
        string $profile,
        string $device,
        string $environment,
        OutputInterface $output,
        bool $requireConfig = true,
    ): array {
        $invrtDir = getenv('INVRT_DIRECTORY') ?: Path::join(
            getenv('INIT_CWD') ?: (string) getcwd(),
            '.invrt',
        );

        $config = $this->readConfig(Path::join($invrtDir, 'config.yaml'), $requireConfig, $output, $profile, $device, $environment);
        $resolved = $this->resolve($config, $profile, $device, $environment);

        $dataDir = Path::join($invrtDir, 'data', $profile, $environment);

        // Export all values for subprocess access
        putenv("INVRT_DIRECTORY=$invrtDir");
        putenv("INVRT_SCRIPTS_DIR={$this->scriptsDir}");
        putenv("INVRT_PROFILE=$profile");
        putenv("INVRT_DEVICE=$device");
        putenv("INVRT_ENVIRONMENT=$environment");
        foreach ($resolved as $key => $value) {
            putenv('INVRT_' . strtoupper($key) . "=$value");
        }

        return [
            'INVRT_PROFILE'                 => $profile,
            'INVRT_DEVICE'                  => $device,
            'INVRT_ENVIRONMENT'             => $environment,
            'INVRT_SCRIPTS_DIR'             => $this->scriptsDir,
            'INVRT_DIRECTORY'               => $invrtDir,
            'INVRT_DATA_DIR'                => $dataDir,
            'INVRT_COOKIES_FILE'            => Path::join($dataDir, 'cookies'),
            'INVRT_CONFIG_FILE'             => Path::join($invrtDir, 'config.yaml'),
            'INVRT_URL'                     => (string) $resolved['url'],
            'INVRT_LOGIN_URL'               => (string) $resolved['login_url'],
            'INVRT_USERNAME'                => (string) $resolved['username'],
            'INVRT_PASSWORD'                => (string) $resolved['password'],
            'INVRT_VIEWPORT_WIDTH'          => (string) $resolved['viewport_width'],
            'INVRT_VIEWPORT_HEIGHT'         => (string) $resolved['viewport_height'],
            'INVRT_MAX_CRAWL_DEPTH'         => (string) $resolved['max_crawl_depth'],
            'INVRT_MAX_PAGES'               => (string) $resolved['max_pages'],
            'INVRT_USER_AGENT'              => (string) $resolved['user_agent'],
            'INVRT_MAX_CONCURRENT_REQUESTS' => (string) $resolved['max_concurrent_requests'],
        ];
    }

    /**
     * Load and process config.yaml via InvrtConfiguration.
     * Returns the processed config array, or [] if not required and file is missing/invalid.
     *
     * @throws \RuntimeException when $required is true and file is missing/invalid
     */
    private function readConfig(
        string $configFile,
        bool $required,
        OutputInterface $output,
        string $profile,
        string $device,
        string $environment,
    ): array {
        if (!file_exists($configFile)) {
            if ($required) {
                throw new \RuntimeException(
                    "Configuration file not found at $configFile. Please run 'invrt init' to initialize the project.",
                );
            }
            return [];
        }

        try {
            $loader = new YamlConfigLoader(new FileLocator(dirname($configFile)));
            $raw = $loader->load(basename($configFile));
            $processed = (new Processor())->processConfiguration(new InvrtConfiguration(), [$raw]);
        } catch (\Exception $e) {
            if ($required) {
                throw new \RuntimeException('Error reading config file: ' . $e->getMessage());
            }
            return [];
        }

        if ($required) {
            $output->writeln(
                "<comment>#  Loading project settings for profile: $profile device: $device environment: $environment</comment>",
                OutputInterface::VERBOSITY_VERBOSE,
            );
        }

        return $processed;
    }

    /**
     * Merge settings → environment → profile → device, then apply credential env var overrides.
     */
    private function resolve(array $config, string $profile, string $device, string $environment): array
    {
        $resolved = [];
        foreach (
            [
                $config['settings'] ?? [],
                $config['environments'][$environment] ?? [],
                $config['profiles'][$profile] ?? [],
                $config['devices'][$device] ?? [],
                InvrtConfiguration::env(),
            ] as $section
        ) {
            foreach (InvrtConfiguration::DEFAULTS as $key => $default) {
                $resolved[$key] = $section[$key] ?? $resolved[$key] ?? $default;
            }
        }

        // Credential env var overrides (highest precedence)
        foreach (['username', 'password'] as $cred) {
            $val = getenv('INVRT_' . strtoupper($cred));
            if ($val !== false && $val !== '') {
                $resolved[$cred] = $val;
            }
        }

        return $resolved;
    }
}
