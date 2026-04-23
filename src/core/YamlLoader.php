<?php

namespace InVRT\Core;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Yaml;

/** Loads and validates a YAML config file against the InVRT schema. */
class YamlLoader extends FileLoader
{
    public function load(mixed $resource, ?string $type = null): array
    {
        $path   = $this->locator->locate($resource);
        $loaded = Yaml::parse((string) file_get_contents($path)) ?: [];
        $parsed = (new Processor())->processConfiguration(new ConfigSchema(), [$loaded]);

        foreach ($parsed as $key => $value) {
            $parsed[$key] = is_array($value) ? array_filter($value) : $value;
        }

        return $parsed;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return is_string($resource) && str_ends_with($resource, '.yaml');
    }

    /**
     * Load and validate a config file.
     *
     * Returns ['data' => array, 'warning' => ?string].
     * On schema validation failure, returns the raw YAML as data and a friendly warning string.
     * YAML parse errors and file-read errors are still thrown.
     */
    public static function fromFile(string $filepath): array
    {
        $locator = new FileLocator(dirname($filepath));
        $loader  = new self($locator);

        try {
            return ['data' => $loader->load($filepath), 'warning' => null];
        } catch (InvalidConfigurationException $e) {
            // Load raw YAML as fallback so execution can continue
            $raw = Yaml::parse((string) file_get_contents($filepath)) ?: [];
            return [
                'data'    => $raw,
                'warning' => 'Config file has unexpected values and may not behave as expected: ' . $e->getMessage(),
            ];
        }
    }
}
