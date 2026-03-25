#!/usr/bin/env php
<?php

require_once __DIR__ . '/invrt-utils.inc.php';

use Symfony\Component\Yaml\Yaml;

$configFile = joinPath($_ENV['INVRT_DIRECTORY'], 'config.yaml');

// Check if config file exists
if (!file_exists($configFile)) {
    echo "# Configuration file not found at: $configFile\n";
    echo "# Run 'invrt init' to create a new configuration.\n";
    return;
}

try {
    $fileContents = file_get_contents($configFile);
    $config = Yaml::parse($fileContents) ?: [];
    
    echo "# Current inVRT Configuration:\n";
    echo "# ============================\n\n";
    
    // Display the configuration in a readable format
    foreach ($config as $section => $values) {
        echo "$section:\n";
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    echo "  $key:\n";
                    foreach ($value as $subkey => $subvalue) {
                        echo "    $subkey: " . (is_array($subvalue) ? json_encode($subvalue) : $subvalue) . "\n";
                    }
                } else {
                    echo "  $key: $value\n";
                }
            }
        }
        echo "\n";
    }
} catch (Exception $error) {
    fwrite(STDERR, "Error reading config file: " . $error->getMessage() . "\n");
    return;
}