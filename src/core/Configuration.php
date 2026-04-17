<?php

namespace InVRT\Core;

use Symfony\Component\Yaml\Yaml;

/**
 * Resolves, holds, and persists inVRT configuration.
 *
 * Accepts a config filepath and an env-var override array. Merges defaults,
 * the YAML file's settings/environments/profiles/devices sections, and the
 * env overrides into a flat INVRT_* array with all placeholder tokens resolved.
 */
class Configuration
{
    /** Flat resolved INVRT_* config. */
    private array $resolved;

    /** Raw parsed YAML structure (preserved for write-back). */
    private array $parsed = [];

    /** Keys mutated via set() that need to be persisted on write(). */
    private array $changes = [];

    private bool $fileExists;

    /**
     * @param string  $filepath  Absolute path to config.yaml (need not exist yet).
     * @param array   $env       Env-var overrides (e.g. from getenv() or test fixtures).
     *                           Must include INVRT_PROFILE, INVRT_ENVIRONMENT, INVRT_DEVICE
     *                           when non-default selection is needed.
     */
    public function __construct(
        private readonly string $filepath,
        private readonly array $env = [],
    ) {
        $this->fileExists = file_exists($filepath);
        $this->parsed     = $this->fileExists ? YamlLoader::fromFile($filepath) : [];
        $this->resolved   = $this->resolve();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->resolved[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->resolved[$key] = $value;
        $this->changes[$key]  = $value;
    }

    /** Returns the full flat resolved config array. */
    public function all(): array
    {
        return $this->resolved;
    }

    /** Exports all resolved values to process environment variables. */
    public function export(): void
    {
        foreach ($this->resolved as $k => $v) {
            putenv("$k=$v");
        }
    }

    public function fileExists(): bool
    {
        return $this->fileExists;
    }

    public function getFilepath(): string
    {
        return $this->filepath;
    }

    /**
     * Persists any set() changes back to the YAML config file.
     * Updates the settings section with the changed keys.
     */
    public function write(): void
    {
        $data = $this->parsed;

        foreach ($this->changes as $invrtKey => $value) {
            $yamlKey = strtolower(str_replace('INVRT_', '', $invrtKey));
            if (array_key_exists($yamlKey, ConfigSchema::DEFAULTS)) {
                $data['settings'][$yamlKey] = $value;
            }
        }

        $dir = dirname($this->filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->filepath, Yaml::dump($data, 4, 2));
    }

    // -------------------------------------------------------------------------

    private function resolve(): array
    {
        $profile     = $this->env['INVRT_PROFILE']     ?? 'anonymous';
        $environment = $this->env['INVRT_ENVIRONMENT'] ?? 'local';
        $device      = $this->env['INVRT_DEVICE']      ?? 'desktop';

        $base = $this->buildDefaults($profile, $environment, $device);

        $settings    = $this->asEnv($this->parsed['settings']    ?? []);
        $envSection  = $this->asEnv($this->parsed['environments'][$environment] ?? []);
        $profSection = $this->asEnv($this->parsed['profiles'][$profile]         ?? []);
        $devSection  = $this->asEnv($this->parsed['devices'][$device]           ?? []);

        // Merge: env overrides win, then device, profile, environment, settings, defaults
        $combined = $this->env
                  + $devSection
                  + $profSection
                  + $envSection
                  + $settings
                  + $base;

        // Keep only INVRT_* keys, then resolve placeholders
        $cleaned = array_filter($combined, fn($k) => str_starts_with($k, 'INVRT_'), ARRAY_FILTER_USE_KEY);

        $result = $this->interpolate($cleaned);

        // Ensure INVRT_CONFIG_FILE always reflects the actual filepath used
        $result['INVRT_CONFIG_FILE'] = $this->filepath;

        return $result;
    }

    private function buildDefaults(string $profile, string $environment, string $device): array
    {
        $base = $this->asEnv(ConfigSchema::DEFAULTS);

        $base['INVRT_PROFILE']     = $profile;
        $base['INVRT_ENVIRONMENT'] = $environment;
        $base['INVRT_DEVICE']      = $device;
        $base['INVRT_CWD']         = $this->env['INVRT_CWD'] ?? (string) getcwd();
        $base['INVRT_COOKIE']      = '';

        return $base;
    }

    /** Prefix bare config keys with INVRT_ if not already prefixed. */
    private function asEnv(array $config): array
    {
        $out = [];
        foreach ($config as $k => $v) {
            if (!str_starts_with($k, 'INVRT_')) {
                $k = 'INVRT_' . strtoupper($k);
            }
            $out[$k] = $v;
        }
        return $out;
    }

    /** Resolve INVRT_XXX placeholder tokens in string values (3 passes for nesting). */
    private function interpolate(array $config): array
    {
        for ($i = 0; $i < 3; $i++) {
            $config = array_map(
                fn($v) => is_string($v) ? strtr($v, $config) : $v,
                $config,
            );
        }
        return $config;
    }
}
