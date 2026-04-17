<?php

namespace InVRT\Core;

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

    public static function fromFile(string $filepath): array
    {
        $locator = new FileLocator(dirname($filepath));
        $loader  = new self($locator);
        return $loader->load($filepath);
    }
}
