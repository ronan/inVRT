<?php

namespace InVRT\Core;

use InVRT\Core\Service\PlanService;
use Symfony\Component\Yaml\Yaml;

/**
 * Resolves, holds, and persists inVRT configuration.
 *
 * Reads the project's plan.yaml via PlanService and merges defaults, the
 * file's project/environments/profiles/devices sections, and env-var
 * overrides into a flat INVRT_* array with placeholder tokens resolved.
 */
class Configuration
{
    /** Flat resolved INVRT_* config. */
    private array $resolved;

    /** Raw parsed plan.yaml structure (preserved for write-back). */
    private array $parsed = [];

    /** Keys mutated via set() that need to be persisted on write(). */
    private array $changes = [];

    private bool $fileExists;

    /**
     * @param string  $filepath  Absolute path to plan.yaml (need not exist yet).
     * @param array   $env       Env-var overrides (e.g. from getenv() or test fixtures).
     *                           Must include INVRT_PROFILE, INVRT_ENVIRONMENT, INVRT_DEVICE
     *                           when non-default selection is needed.
     */
    public function __construct(
        private readonly string $filepath,
        private readonly array $env = [],
    ) {
        $this->fileExists = file_exists($filepath);

        if ($this->fileExists) {
            $this->parsed = PlanService::read($filepath);
        }

        $this->resolved = $this->resolve();
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

    /** Returns a top-level raw parsed section (e.g. 'environments', 'profiles', 'devices', 'name'). */
    public function getSection(string $key): mixed
    {
        return $this->parsed[$key] ?? null;
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

    /** No-op; retained for compatibility with callers expecting warnings. */
    public function getWarnings(): array
    {
        return [];
    }

    public function getFilepath(): string
    {
        return $this->filepath;
    }

    /**
     * Persists any set() changes back to the plan.yaml file.
     * Updates the project section with the changed keys.
     */
    public function write(): void
    {
        $data = $this->parsed;

        foreach ($this->changes as $invrtKey => $value) {
            $yamlKey = strtolower(str_replace('INVRT_', '', $invrtKey));
            if (array_key_exists($yamlKey, ConfigSchema::DEFAULTS)) {
                $data['project'][$yamlKey] = $value;
            }
        }

        $dir = dirname($this->filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->filepath, Yaml::dump($data, 6, 2));
    }

    // -------------------------------------------------------------------------

    private function resolve(): array
    {
        $profile     = $this->env['INVRT_PROFILE']     ?? 'anonymous';
        $environment = $this->env['INVRT_ENVIRONMENT'] ?? 'local';
        $device      = $this->env['INVRT_DEVICE']      ?? 'desktop';

        $base = $this->buildDefaults($profile, $environment, $device);

        $project     = $this->parsed['project'] ?? [];
        unset($project['name']);
        $settings    = $this->asEnv($project);
        $envSection  = $this->asEnv($this->parsed['environments'][$environment] ?? []);
        $profSection = $this->asEnv($this->parsed['profiles'][$profile]         ?? []);
        $devSection  = $this->asEnv($this->parsed['devices'][$device]           ?? []);

        // Merge: env overrides win, then device, profile, environment, project, defaults
        $combined = $this->env
                  + $devSection
                  + $profSection
                  + $envSection
                  + $settings
                  + $base;

        // Keep only INVRT_* keys, then resolve placeholders
        $cleaned = array_filter($combined, fn($k) => str_starts_with($k, 'INVRT_'), ARRAY_FILTER_USE_KEY);

        $result = $this->interpolate($cleaned);

        // Ensure INVRT_PLAN_FILE always reflects the actual filepath used.
        $result['INVRT_PLAN_FILE'] = $this->filepath;

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
