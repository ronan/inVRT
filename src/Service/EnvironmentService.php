<?php

namespace App\Service;

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

    public function __construct(
        string $profile = 'anonymous',
        string $device = 'desktop',
        string $environment = 'local'
    ) {
        $this->profile = $profile;
        $this->device = $device;
        $this->environment = $environment;
        $this->scriptsDir = __DIR__ . '/..';
    }

    /**
     * Initialize environment variables and load configuration
     * Should be called before executing a command that needs configuration
     * 
     * @param bool $requireConfig If true, throws when config file is missing
     *                           If false, just sets up defaults
     * @throws \RuntimeException When $requireConfig is true and file doesn't exist
     */
    public function initialize(OutputInterface $output, bool $requireConfig = true): array
    {
        $this->setupDirectories();
        
        if ($requireConfig) {
            $this->loadConfigFile($output);
        } else {
            // Still try to load config if it exists, but don't print loading message
            $configFile = $this->joinPath($this->invrtDirectory, 'config.yaml');
            if (file_exists($configFile)) {
                try {
                    $fileContents = file_get_contents($configFile);
                    $this->config = Yaml::parse($fileContents) ?: [];
                } catch (\Exception $error) {
                    // Silently ignore parsing errors when requireConfig is false
                    $this->config = [];
                }
            }
        }

        return $this->getEnvironmentArray();
    }

    /**
     * Set up all directory paths
     */
    private function setupDirectories(): void
    {
        // Get INVRT_DIRECTORY from env or set to .invrt in current directory
        if (getenv('INVRT_DIRECTORY')) {
            $this->invrtDirectory = getenv('INVRT_DIRECTORY');
        } else {
            $currentDir = getenv('INIT_CWD') ?: getcwd();
            $this->invrtDirectory = $this->joinPath($currentDir, '.invrt');
        }

        // Set environment variables in both $_ENV and putenv for subprocess access
        $_ENV['INVRT_DIRECTORY'] = $this->invrtDirectory;
        $_ENV['INVRT_SCRIPTS_DIR'] = $this->scriptsDir;
        $_ENV['INVRT_PROFILE'] = $this->profile;
        $_ENV['INVRT_DEVICE'] = $this->device;
        $_ENV['INVRT_ENVIRONMENT'] = $this->environment;

        putenv('INVRT_DIRECTORY=' . $this->invrtDirectory);
        putenv('INVRT_SCRIPTS_DIR=' . $this->scriptsDir);
        putenv('INVRT_PROFILE=' . $this->profile);
        putenv('INVRT_DEVICE=' . $this->device);
        putenv('INVRT_ENVIRONMENT=' . $this->environment);
    }

    /**
     * Load and parse the config.yaml file
     */
    private function loadConfigFile(OutputInterface $output): void
    {
        $configFile = $this->joinPath($this->invrtDirectory, 'config.yaml');

        if (!file_exists($configFile)) {
            throw new \RuntimeException(
                "Configuration file not found at $configFile. Please run 'invrt init' to initialize the project."
            );
        }

        $output->writeln(
            "<comment>#  Loading project settings for profile: {$this->profile} "
            . "device: {$this->device} "
            . "environment: {$this->environment}</comment>",
            OutputInterface::VERBOSITY_VERBOSE
        );

        try {
            $fileContents = file_get_contents($configFile);
            $this->config = Yaml::parse($fileContents) ?: [];
        } catch (\Exception $error) {
            throw new \RuntimeException("Error reading config file: " . $error->getMessage());
        }

        // Load config values and set environment variables
        $configKeys = [
            'profile' => 'anonymous',
            'device' => 'desktop',
            'environment' => 'local',
            'directory' => './.invrt',
            'data_dir' => 'data',
            'cookies_file' => $this->joinPath('data', $this->profile, $this->environment, 'cookies.txt'),
            'url' => '',
            'login_url' => '',
            'max_crawl_depth' => 3,
            'max_pages' => 100,
            'user_agent' => 'InVRT/1.0',
            'max_concurrent_requests' => 5,
            'username' => '',
            'password' => '',
            'viewport_width' => 1920,
            'viewport_height' => 1080,
        ];

        // Initialize accumulated config values
        $accumulatedConfig = array_fill_keys($configKeys, null);

        // Load config values for the current profile, device, and environment
        // Process in order of precedence: environment > profile > device
        $sections = [
            "environments.{$this->environment}",
            "profiles.{$this->profile}",
            "devices.{$this->device}",
        ];

        foreach ($sections as $section) {
            $sectionValue = $this->getConfigValueRaw($section);
            if (!is_array($sectionValue) || empty($sectionValue)) {
                $output->writeln("#⚠️ Section $section not found in config.yaml, using defaults\n",
                OutputInterface::VERBOSITY_VERBOSE
                );
                continue;
            }

            foreach ($configKeys as $key => $default) {
                $value = $this->getConfigValue("$section.$key", null);
                
                // Only set if we got a non-null value from the config
                // This allows environment settings to override profile/device settings
                if ($value !== null) {
                    $accumulatedConfig[$key] = $value;
                }
            }
        }

        // Now set environment variables for all accumulated values, using defaults for missing ones
        foreach ($configKeys as $key => $default) {
            $envVarName = 'INVRT_' . strtoupper($key);
            $value = $accumulatedConfig[$key] ?? $default;
            putenv("$envVarName=$value");
            $_ENV[$envVarName] = $value;
        }
    }

    /**
     * Get a nested config value without converting to string
     */
    private function getConfigValueRaw(string $key)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Get a nested config value using dot notation
     * Returns string when found, null when not found
     */
    private function getConfigValue(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        // Return null for missing values so we can use it as a signal
        if ($value === null || $value === false) {
            return $default;
        }
        
        return (string)$value;
    }

    /**
     * Get all environment variables as an array for passing to shell scripts
     */
    public function getEnvironmentArray(): array
    {
        return [
            'INVRT_PROFILE' => $this->profile,
            'INVRT_DEVICE' => $this->device,
            'INVRT_ENVIRONMENT' => $this->environment,
            'INVRT_SCRIPTS_DIR' => $this->scriptsDir,
            'INVRT_DIRECTORY' => $this->invrtDirectory,
            'INVRT_DATA_DIR' => $this->joinPath($this->invrtDirectory, 'data', $this->profile, $this->environment),
            'INVRT_COOKIES_FILE' => $this->joinPath($this->invrtDirectory, 'data', $this->profile, $this->environment, 'cookies'),
            'INVRT_CONFIG_FILE' => $this->joinPath($this->invrtDirectory, 'config.yaml'),
            'INVRT_USERNAME' => getenv('INVRT_USERNAME') ?: '',
            'INVRT_PASSWORD' => getenv('INVRT_PASSWORD') ?: '',
            'INVRT_URL' => getenv('INVRT_URL') ?: '',
        ];
    }

    /**
     * Get a specific environment variable
     */
    public function getEnv(string $key): string
    {
        $env = $this->getEnvironmentArray();
        return $env[$key] ?? '';
    }

    /**
     * Join file path segments
     */
    private function joinPath(...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
