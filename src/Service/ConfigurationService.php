<?php

namespace App\Service;

use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Filesystem\Path;

class ConfigurationService
{
    public string $profile = '';
    public string $environment = '';
    public string $device = '';

    public array $config;

    public function __construct()
    {
        $this->config = $this->options() + $this->defaults();
    }

    /**
     * Get a list of valid INVRT_ config keys.
     *
     * @return array
     */
    public function keys(): array
    {
        return array_keys($this->defaults());
    }

    /**
     * Set the passed in options. This will affect what is loaded later.
     *
     * @param string $environment
     * @param string $profile
     * @param string $device
     * @return array{device: string, environment: string, profile: string}
     */
    public function options(
        string $environment = 'local',
        string $profile = 'anonymous',
        string $device = 'desktop',
    ): array {
        $this->profile      = $profile ?: $this->profile;
        $this->environment  = $environment ?: $this->environment;
        $this->device       = $device ?: $this->device;

        return $this->interpolate($this->defaults());
    }

    public function defaults(): array
    {
        // Add the schema defaults, prioritize the environment values.
        $base = $this->as_env(InvrtConfiguration::DEFAULTS);

        $base['INVRT_PROFILE']          = $this->profile ?: 'anonymous';
        $base['INVRT_ENVIRONMENT']      = $this->environment ?: 'local';
        $base['INVRT_DEVICE']           = $this->device ?: 'desktop';

        $base['INVRT_CWD']              = getenv('INVRT_CWD') ?: (string) getcwd();

        $base['INVRT_COOKIE']           = '';

        return $base;
    }

    protected function interpolate(array $config): array
    {
        // Run the translation 3 times to allow nested placeholders to be replaced
        for ($i = 0; $i < 3; $i++) {
            $config = array_map(fn($value) => is_string($value) ? strtr($value, $config) : $value, $config);
        }
        return $config;
    }

    /**
     * Load the configuration from the environment and disk and export it to environment variables
     *
     * @return array
     */
    public function load(): array
    {
        // Get the defaults and environment variables to load the config file path.
        $defaults = $this->defaults();
        $getenv = $this->from_env();

        $INVRT_CONFIG_FILE = $this->interpolate($getenv + $defaults)['INVRT_CONFIG_FILE'];
        $parsed     = $INVRT_CONFIG_FILE ? $this->from_file($INVRT_CONFIG_FILE) : [];

        $settings    = $this->as_env($parsed['settings'] ?? []);
        $environment = $this->as_env($parsed['environments'][$this->environment] ?? []);
        $device      = $this->as_env($parsed['devices'][$this->device] ?? []);
        $profile     = $this->as_env($parsed['profiles'][$this->profile] ?? []);

        // Combine the sources of config in most to least preference order.
        $combined  = $getenv
                    + $device
                    + $profile
                    + $environment
                    + $settings
                    + $defaults;

        // One last key clean and replace INVRT_XXX placeholders with the resolved values
        $cleaned = array_filter($combined, fn($key) => strpos($key, 'INVRT_') === 0, ARRAY_FILTER_USE_KEY);
        $final = $this->interpolate($cleaned);

        $this->export_to_env($final);

        return $final;
    }

    /**
     * Load the persistent configuration file from disk
     *
     * @return array
     */
    public function from_file(string $INVRT_CONFIG_FILE): array
    {
        if (!file_exists($INVRT_CONFIG_FILE)) {
            throw new FileLocatorFileNotFoundException("Configuration file not found: $INVRT_CONFIG_FILE");
        }
        $locator = new FileLocator(Path::getDirectory($INVRT_CONFIG_FILE));
        $loader  = new YamlConfigLoader($locator);
        $parsed  = $loader->load($INVRT_CONFIG_FILE);
        return $parsed;
    }

    /**
     * Load all of the allowed keys using `getenv`
     *
     * @param array|null $keys
     * @return array
     */
    public function from_env(?array $keys = null): array
    {
        $keys ??= $this->keys();

        return array_intersect_key(getenv(), array_flip($keys));
    }

    /**
     * Export a configuration array to environment variables using putenv
     *
     * @param array|null $config
     * @return void
     */
    public function export_to_env(?array $config = null): void
    {
        $config ??= $this->defaults() ;

        $env = $this->as_env($config);
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }


    /**
     * Returns a config array with ENV_VAR type keys (INVRT_XXX)
     *
     * @param array $config
     * @return array
     */
    public function as_env($config = null): array
    {
        $config ??= $this->defaults();

        $out = [];
        foreach ($config as $k => $v) {
            strpos($k, 'INVRT_') === 0 || $k = 'INVRT_' . strtoupper($k);
            $out[$k] = $v;
        }

        return $out;
    }
}
