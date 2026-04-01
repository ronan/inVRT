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

        $base['INVRT_DIRECTORY']        = 'INVRT_CWD/.invrt';
        $base['INVRT_CONFIG_FILE']      = 'INVRT_DIRECTORY/config.yaml';
        $base['INVRT_SCRIPTS_DIR']      = 'INVRT_DIRECTORY/scripts';

        $base['INVRT_ENVIRONMENT_DIR']  = 'INVRT_DIRECTORY/data/INVRT_ENVIRONMENT';
        $base['INVRT_PROFILE_DIR']      = 'INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE';
        $base['INVRT_DEVICE_DIR']       = 'INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE/INVRT_DEVICE';

        $base['INVRT_DATA_DIR']         = 'INVRT_DEVICE_DIR';
        $base['INVRT_CAPTURE_DIR']      = 'INVRT_DEVICE_DIR';

        $base['INVRT_CRAWL_DIR']        = 'INVRT_PROFILE_DIR';
        $base['INVRT_COOKIES_FILE']     = 'INVRT_CRAWL_DIR/cookies';
        $base['INVRT_CRAWL_LOG']        = 'INVRT_CRAWL_DIR/logs/crawl.log';
        $base['INVRT_CLONE_DIR']        = 'INVRT_CRAWL_DIR/clone';
        $base['INVRT_CRAWL_FILE']       = 'INVRT_CRAWL_DIR/crawled_urls.txt';
        $base['INVRT_EXCLUDE_FILE']     = 'INVRT_CRAWL_DIR/exclude_paths.txt';


        $base['INVRT_COOKIE']           = '';

        return $base;
    }

    protected function interpolate(array $config): array
    {
        // $last = [];
        // $last = $config;
        // // while ($config !== $last) {
        // // }
        for ($i = 0; $i < 3; $i++) {
            $config = array_map(fn($value) => is_string($value) ? strtr($value, $config) : $value, $config);
        }
        // Run the translation over and over until no more replacements occur
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

    /**
     * Initialise environment, load + resolve config, export env vars for subprocesses.
     *
     * @throws \RuntimeException when $requireConfig is true and file is missing/invalid
     */
    public function get(): array
    {
        $base = [];

        // Passed in arguments take precedence over environment variables
        $base['profile']            = $this->profile ?: getenv('INVRT_PROFILE');
        $base['device']             = $this->device ?: getenv('INVRT_DEVICE');
        $base['environment']        = $this->environment ?: getenv('INVRT_ENVIRONMENT');

        // Add the defaults, prioritize the environment values.
        $base += $this->defaults();

        // Parse the persistent configuration file
        $final = $this->load();

        return [
            'INVRT_PROFILE'                 => (string) $final['profile'],
            'INVRT_DEVICE'                  => (string) $final['device'],
            'INVRT_ENVIRONMENT'             => (string) $final['environment'],

            'INVRT_CWD'                     => (string) $final['cwd'],
            'INVRT_DIRECTORY'               => (string) $final['directory'],
            'INVRT_PROFILE_DIR'             => (string) $final['profile_dir'],
            'INVRT_ENVIRONMENT_DIR'         => (string) $final['environment_dir'],
            'INVRT_EXCLUDE_FILE'            => (string) $final['exclude_file'],
            'INVRT_DATA_DIR'                => (string) $final['data_dir'],
            'INVRT_CLONE_DIR'               => (string) $final['clone_dir'],
            'INVRT_SCRIPTS_DIR'             => (string) $final['scripts_dir'],
            'INVRT_COOKIES_FILE'            => (string) $final['cookies_file'],
            'INVRT_CRAWL_DIR'               => (string) $final['crawl_dir'],
            'INVRT_CRAWL_FILE'              => (string) $final['crawl_file'],
            'INVRT_CRAWL_LOG'               => (string) $final['crawl_log'],
            'INVRT_COOKIE'                  => (string) $final['cookie'],
            'INVRT_CONFIG_FILE'             => (string) $final['config_file'],

            'INVRT_URL'                     => (string) $final['url'],
            'INVRT_LOGIN_URL'               => (string) $final['login_url'],
            'INVRT_USERNAME'                => (string) $final['username'],
            'INVRT_PASSWORD'                => (string) $final['password'],
            'INVRT_VIEWPORT_WIDTH'          => (string) $final['viewport_width'],
            'INVRT_VIEWPORT_HEIGHT'         => (string) $final['viewport_height'],
            'INVRT_MAX_CRAWL_DEPTH'         => (string) $final['max_crawl_depth'],
            'INVRT_MAX_PAGES'               => (string) $final['max_pages'],
            'INVRT_USER_AGENT'              => (string) $final['user_agent'],
            'INVRT_MAX_CONCURRENT_REQUESTS' => (string) $final['max_concurrent_requests'],
        ];
    }
}
