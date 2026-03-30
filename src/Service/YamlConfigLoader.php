<?php

namespace App\Service;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Yaml;

/** Loads and parses a YAML config file into a raw array. */
class YamlConfigLoader extends FileLoader
{
    public function load(mixed $resource, ?string $type = null): array
    {
        $path    = $this->locator->locate($resource);
        $loaded  = Yaml::parse((string) file_get_contents($path)) ?: [];
        $parser  = new InvrtConfiguration();
        $parsed  = (new Processor())->processConfiguration($parser, [$loaded]);
        return $parsed;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return is_string($resource) && str_ends_with($resource, '.yaml');
    }
}
