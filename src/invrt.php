#!/usr/bin/env php
<?php
// inVRT CLI - Visual Regression Testing Tool

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/invrt-utils.inc.php';

use Symfony\Component\Yaml\Yaml;
use League\CLImate\CLImate;

// Initialize CLImate
$climate = new CLImate();

// Set default values
$_ENV['INVRT_PROFILE']      = 'anonymous';
$_ENV['INVRT_DEVICE']       = 'desktop';
$_ENV['INVRT_ENVIRONMENT']  = 'local';

// Get the scripts directory
$_ENV['INVRT_SCRIPTS_DIR']  = __DIR__;
putenv('INVRT_SCRIPTS_DIR=' . $_ENV['INVRT_SCRIPTS_DIR']);

// Get the INVRT_DIRECTORY
if (getenv('INVRT_DIRECTORY')) {
    $_ENV['INVRT_DIRECTORY'] = getenv('INVRT_DIRECTORY');
} else {
    $currentDir = getenv('INIT_CWD') ? getenv('INIT_CWD') : getcwd();
    $_ENV['INVRT_DIRECTORY'] = joinPath($currentDir, '.invrt');
}

// Define CLImate options (named arguments only)
$climate->arguments->add([
    'profile' => [
        'prefix'      => 'p',
        'longPrefix'  => 'profile',
        'description' => 'Profile name',
        'defaultValue' => 'anonymous',
    ],
    'device' => [
        'prefix'      => 'd',
        'longPrefix'  => 'device',
        'description' => 'Device type',
        'defaultValue' => 'desktop',
    ],
    'environment' => [
        'prefix'      => 'e',
        'longPrefix'  => 'environment',
        'description' => 'Environment name',
        'defaultValue' => 'local',
    ],
    'help' => [
        'longPrefix'  => 'help',
        'description' => 'Show help information',
        'noValue'     => true,
    ],
]);

try {
    $climate->arguments->parse();
} catch (\Exception $exception) {
    $climate->error($exception->getMessage());
    exit(1);
}

// Get the command from the first positional argument (non-flag argument)
// Filter out all arguments starting with '-' to allow flags before the command
$positionalArgs = array_values(array_filter($_SERVER['argv'], function($arg) {
    return strpos($arg, '-') !== 0;
}));
$command = $positionalArgs[1] ?? '';

// Check for help flag
if ($climate->arguments->get('help') || !$command || $command === 'help') {
    showHelp();
    exit(0);
}

// Get options with CLImate
$_ENV['INVRT_PROFILE'] = $climate->arguments->get('profile');
$_ENV['INVRT_DEVICE'] = $climate->arguments->get('device');
$_ENV['INVRT_ENVIRONMENT'] = $climate->arguments->get('environment');

// Check for invalid commands
if (!in_array($command, ['init', 'crawl', 'reference', 'test', 'config', 'help'])) {
    $climate->error("Invalid command: \"" . $command . "\". Use \"invrt help\" for usage information.");
    exit(1);
}

// Set up the data directory and cookies file path
$_ENV['INVRT_DATA_DIR'] = joinPath($_ENV['INVRT_DIRECTORY'], 'data', $_ENV['INVRT_PROFILE'], $_ENV['INVRT_ENVIRONMENT']);
$_ENV['INVRT_COOKIES_FILE'] = joinPath($_ENV['INVRT_DATA_DIR'], 'cookies.json');

// Load profile-specific settings and override defaults
if ($command != 'init') {
    $_ENV['INVRT_CONFIG_FILE'] = joinPath($_ENV['INVRT_DIRECTORY'], 'config.yaml');

    if (!file_exists($_ENV['INVRT_CONFIG_FILE'])) {
        $climate->error("Configuration file not found at " . $_ENV['INVRT_CONFIG_FILE'] . ". Please run 'invrt init' to initialize the project.");
        exit(1);
    }

    $climate->comment("#  Loading project settings for profile: " .  $_ENV['INVRT_PROFILE'] .
        " device: " . $_ENV['INVRT_DEVICE'] .
        " environment: " . $_ENV['INVRT_ENVIRONMENT']);

    loadConfig($_ENV['INVRT_CONFIG_FILE']);
}


// Execute the command with login handling
executeCommand($command, $_ENV);

// Execute the command
function executeCommand($command, $env) {
    // Extract values from environment
    $INVRT_USERNAME = $env['INVRT_USERNAME'] ?? '';
    $INVRT_PASSWORD = $env['INVRT_PASSWORD'] ?? '';
    $INVRT_URL = $env['INVRT_URL'] ?? '';
    $INVRT_COOKIES_FILE = $env['INVRT_COOKIES_FILE'] ?? '';
    $INVRT_DIRECTORY = $env['INVRT_DIRECTORY'] ?? '';
    
    // Login before executing crawl, reference, or test commands
    if (($command === 'crawl' || $command === 'reference' || $command === 'test') && ($INVRT_USERNAME || $INVRT_PASSWORD)) {
        loginIfCredentialsExist($INVRT_USERNAME, $INVRT_PASSWORD, $INVRT_URL, $INVRT_COOKIES_FILE, $INVRT_DIRECTORY);
    }

    // Execute command
    switch ($command) {
        case 'init':
            executeShellScript('invrt-init.sh', $env);
            break;
        case 'crawl':
            executeShellScript('invrt-crawl.sh', $env);
            break;
        case 'reference':
            executeShellScript('invrt-reference.sh', $env);
            break;
        case 'test':
            executeShellScript('invrt-test.sh', $env);
            break;
        case 'config':
            include __DIR__ . '/invrt-config.php';
            break;
        default:
            $climate = new CLImate();
            $climate->error("Unknown command: \"" . $command . "\"");
            exit(1);
    }
}

?>
