<?php

namespace App\Service;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

class EnvironmentService
{
    public function __construct() {}

    /**
     * Initialise environment, load + resolve config, export env vars for subprocesses.
     *
     * @throws \RuntimeException when $requireConfig is true and file is missing/invalid
     */
    public function initialize(
        string $profile,
        string $device,
        string $env,
        OutputInterface $output,
        bool $requireConfig = true,
    ): array {
        $base = [];

        // Passed in arguments take precedence over environment variables
        $base['profile']        = $profile ?: getenv('INVRT_PROFILE');
        $base['device']         = $device ?: getenv('INVRT_DEVICE');
        $base['environment']    = $env ?: getenv('INVRT_ENVIRONMENT');

        $base['cwd']            = getenv('INVRT_CWD') ?: (string) getcwd();
        $base['directory']      = getenv('INVRT_DIRECTORY') ?: Path::join($base['cwd'], '.invrt');
        $base['config_file']    = getenv('INVRT_CONFIG_FILE') ?: Path::join($base['directory'], 'config.yaml');


        $data_subdir = Path::join('data', $base['environment'], $base['profile']);
        $base['data_dir']       = getenv('INVRT_DATA_DIR') ?: Path::join($base['directory'], $data_subdir);
        $base['cookies_file']   = getenv('INVRT_COOKIES_FILE') ?: Path::join($base['data_dir'], 'cookies');

        $base += InvrtConfiguration::DEFAULTS;


        // Load, validate and parse the .invrt/config.yaml file.
        try {
            $locator = new FileLocator($base['directory']);
            $loader  = new YamlConfigLoader($locator);
            $parsed  = $loader->load($base['config_file']);
        } catch (\Symfony\Component\Config\Exception\FileLocatorFileNotFoundException $e) {
            if ($requireConfig) {
                throw new \RuntimeException('Could not find a config.yml file at: ' . $base['config_file'] . '. Run `invrt init` to get started.');
            }
            return $this->configToEnvKeyedArray($base);
        } catch (\Exception $e) {
            if ($requireConfig) {
                throw new \RuntimeException('Error reading config file: ' . $base['config_file'] . ': ' . $e->getMessage());
            }
            return $this->configToEnvKeyedArray($base);
        }

        $output->writeln(
            "<comment>#  Loading project settings for profile: $profile device: $device environment: $env</comment>",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        // Get environment variables that start with INVRT_ and map them to the keys in $base
        $getenv = array_filter(
            array_combine(
                array_keys($base),
                array_map(
                    fn($key) => getenv('INVRT_' . strtoupper($key)),
                    array_keys($base),
                ),
            ),
        );

        $final = [];
        foreach (
            [
                $base,
                array_filter($parsed['settings'] ?? []),
                array_filter($parsed['environments'][$env] ?? []),
                array_filter($parsed['profiles'][$profile] ?? []),
                array_filter($parsed['devices'][$device] ?? []),
                $getenv,
            ] as $section
        ) {
            foreach (array_keys($base) as $key) {
                $final[$key] = $section[$key] ?? $final[$key] ?? null;
                putenv('INVRT_' . strtoupper($key) . "=$final[$key]");
            }
        }
        return [
            'INVRT_DIRECTORY'               => (string) $final['directory'],
            'INVRT_DATA_DIR'                => (string) $final['data_dir'],
            'INVRT_PROFILE'                 => (string) $final['profile'],
            'INVRT_DEVICE'                  => (string) $final['device'],
            'INVRT_ENVIRONMENT'             => (string) $final['environment'],
            'INVRT_SCRIPTS_DIR'             => (string) $final['scripts_dir'],
            'INVRT_COOKIES_FILE'            => (string) $final['cookies_file'],
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

    private function configToEnvKeyedArray(array $config): array
    {
        $keys = array_map(fn($k) => 'INVRT_' . strtoupper($k), array_keys($config));
        return array_combine($keys, $config);
    }
}
