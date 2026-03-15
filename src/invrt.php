#!/usr/bin/env php
<?php
// inVRT CLI - Visual Regression Testing Tool

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/invrt-utils.inc.php';

use Symfony\Component\Yaml\Yaml;

// Parse optional arguments for profile, device, and environment
$_ENV['INVRT_PROFILE'] = 'default';
$_ENV['INVRT_DEVICE'] = 'desktop';
$_ENV['INVRT_ENVIRONMENT'] = 'local';

// Get the scripts directory
$_ENV['INVRT_SCRIPTS_DIR'] = __DIR__;

// Get the INVRT_DIRECTORY
if (getenv('INVRT_DIRECTORY')) {
    $_ENV['INVRT_DIRECTORY'] = getenv('INVRT_DIRECTORY');
} else {
    $currentDir = getenv('INIT_CWD') ? getenv('INIT_CWD') : getcwd();
    $_ENV['INVRT_DIRECTORY'] = joinPath($currentDir, '.invrt');
}
echo "📁 INVRT_DIRECTORY: " . $_ENV['INVRT_DIRECTORY'] . "\n";

// Set up the data directory and cookies file path
$_ENV['INVRT_DATA_DIR'] = joinPath($_ENV['INVRT_DIRECTORY'], 'data', $_ENV['INVRT_PROFILE'], $_ENV['INVRT_ENVIRONMENT']);
$_ENV['INVRT_COOKIES_FILE'] = joinPath($_ENV['INVRT_DATA_DIR'], 'cookies.json');

// Get the command from arguments
$command = $argc > 1 ? $argv[1] : '';

// Parse optional arguments for profile, device, and environment
for ($i = 2; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (strpos($arg, '--profile=') === 0) {
        $_ENV['INVRT_PROFILE'] = substr($arg, 10);
    } elseif (strpos($arg, '-p=') === 0) {
        $_ENV['INVRT_PROFILE'] = substr($arg, 3);
    } elseif ($arg === '--profile' && $i + 1 < $argc) {
        $_ENV['INVRT_PROFILE'] = $argv[++$i];
    } elseif ($arg === '-p' && $i + 1 < $argc) {
        $_ENV['INVRT_PROFILE'] = $argv[++$i];
    } elseif (strpos($arg, '--device=') === 0) {
        $_ENV['INVRT_DEVICE'] = substr($arg, 9);
    } elseif (strpos($arg, '-d=') === 0) {
        $_ENV['INVRT_DEVICE'] = substr($arg, 3);
    } elseif ($arg === '--device' && $i + 1 < $argc) {
        $_ENV['INVRT_DEVICE'] = $argv[++$i];
    } elseif ($arg === '-d' && $i + 1 < $argc) {
        $_ENV['INVRT_DEVICE'] = $argv[++$i];
    } elseif (strpos($arg, '--environment=') === 0) {
        $_ENV['INVRT_ENVIRONMENT'] = substr($arg, 14);
    } elseif (strpos($arg, '-e=') === 0) {
        $_ENV['INVRT_ENVIRONMENT'] = substr($arg, 3);
    } elseif ($arg === '--environment' && $i + 1 < $argc) {
        $_ENV['INVRT_ENVIRONMENT'] = $argv[++$i];
    } elseif ($arg === '-e' && $i + 1 < $argc) {
        $_ENV['INVRT_ENVIRONMENT'] = $argv[++$i];
    }
}

echo "📋 Profile: " . $_ENV['INVRT_PROFILE'] . ", Device: " . $_ENV['INVRT_DEVICE'] . ", Environment: " . $_ENV['INVRT_ENVIRONMENT'] . "\n";

// Check for help command or show help if no command
if (!$command || $command === 'help' || $command === '--help' || $command === '-h') {
    showHelp();
}

// Check for invalid commands
if (!in_array($command, ['init', 'crawl', 'reference', 'test'])) {
    fwrite(STDERR, "❌ Invalid command: \"" . $command . "\". Use \"invrt help\" for usage information.\n");
    exit(1);
}

// Read the config for the current project
$CONFIG_FILE = joinPath($_ENV['INVRT_DIRECTORY'], 'config.yaml');

$config = [];
if (!file_exists($CONFIG_FILE) && $command !== 'init') {
    fwrite(STDERR, "❌ Configuration file not found at " . $CONFIG_FILE . ". Please run 'invrt init' to initialize the project.\n");
    exit(1);
}

if (file_exists($CONFIG_FILE)) {
    try {
        $fileContents = file_get_contents($CONFIG_FILE);
        $config = Yaml::parse($fileContents) ?: [];
    } catch (Exception $error) {
        fwrite(STDERR, "❌ Error reading config file: " . $error->getMessage() . "\n");
        exit(1);
    }
}

$config_keys = [
    'url' => '',
    'max_crawl_depth' => 3,
    'max_pages' => 100,
    'user_agent' => 'InVRT/1.0',
    'max_concurrent_requests' => 5,
    'username' => '',
    'password' => '',
    'viewport_width' => 1920,
    'viewport_height' => 1080,
];

// Load profile-specific settings and override defaults
echo "⚙️  Loading profile settings for '" . $_ENV['INVRT_PROFILE'] . "'\n";
echo "⚙️  Loading environment settings for '" . $_ENV['INVRT_ENVIRONMENT'] . "'\n";
echo "⚙️  Loading device settings for '" . $_ENV['INVRT_DEVICE'] . "'\n";

// Read the config values for the current profile, device and environment
foreach ([
        "project",
        "environments.$_ENV[INVRT_ENVIRONMENT]",
        "profiles.$_ENV[INVRT_PROFILE]",
        "devices.$_ENV[INVRT_DEVICE]", 
    ] as $section) {
    if (empty(getConfig($config, $section))) {
        echo "⚠️  Section '" . $section . "' not found in config.yaml, using defaults\n";
    }

    foreach ($config_keys as $key => $default) {
        $env_var_name = 'INVRT_' . strtoupper($key);
        $_ENV[$env_var_name] = getConfig($config, "$section.$key", $_ENV[$env_var_name] ?? $default);
    }
}

// Execute the command with login handling
executeCommand($command, $_ENV);

?>
