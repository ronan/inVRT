#!/usr/bin/env php
<?php
// inVRT CLI - Visual Regression Testing Tool

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/invrt-utils.inc.php';

use Symfony\Component\Yaml\Yaml;

// Parse optional arguments for profile, device, and environment
$INVRT_PROFILE = 'default';
$INVRT_DEVICE = 'desktop';
$INVRT_ENVIRONMENT = 'local';

// Get the INVRT_DIRECTORY
if (getenv('INVRT_DIRECTORY')) {
    $INVRT_DIRECTORY = getenv('INVRT_DIRECTORY');
} else {
    $initCwd = getenv('INIT_CWD');
    $currentDir = getcwd();
    $INVRT_DIRECTORY = ($initCwd ? $initCwd : $currentDir) . DIRECTORY_SEPARATOR . '.invrt';
}
echo "📁 INVRT_DIRECTORY: " . $INVRT_DIRECTORY . "\n";

// Get the command from arguments
$command = $argc > 1 ? $argv[1] : '';

for ($i = 2; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (strpos($arg, '--profile=') === 0) {
        $INVRT_PROFILE = substr($arg, 10);
    } elseif (strpos($arg, '-p=') === 0) {
        $INVRT_PROFILE = substr($arg, 3);
    } elseif ($arg === '--profile' && $i + 1 < $argc) {
        $INVRT_PROFILE = $argv[++$i];
    } elseif ($arg === '-p' && $i + 1 < $argc) {
        $INVRT_PROFILE = $argv[++$i];
    } elseif (strpos($arg, '--device=') === 0) {
        $INVRT_DEVICE = substr($arg, 9);
    } elseif (strpos($arg, '-d=') === 0) {
        $INVRT_DEVICE = substr($arg, 3);
    } elseif ($arg === '--device' && $i + 1 < $argc) {
        $INVRT_DEVICE = $argv[++$i];
    } elseif ($arg === '-d' && $i + 1 < $argc) {
        $INVRT_DEVICE = $argv[++$i];
    } elseif (strpos($arg, '--environment=') === 0) {
        $INVRT_ENVIRONMENT = substr($arg, 14);
    } elseif (strpos($arg, '-e=') === 0) {
        $INVRT_ENVIRONMENT = substr($arg, 3);
    } elseif ($arg === '--environment' && $i + 1 < $argc) {
        $INVRT_ENVIRONMENT = $argv[++$i];
    } elseif ($arg === '-e' && $i + 1 < $argc) {
        $INVRT_ENVIRONMENT = $argv[++$i];
    }
}

echo "📋 Profile: " . $INVRT_PROFILE . ", Device: " . $INVRT_DEVICE . ", Environment: " . $INVRT_ENVIRONMENT . "\n";

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
$CONFIG_FILE = $INVRT_DIRECTORY . DIRECTORY_SEPARATOR . 'config.yaml';

if (!file_exists($CONFIG_FILE) && $command !== 'init') {
    fwrite(STDERR, "❌ Configuration file not found at " . $CONFIG_FILE . ". Please run 'invrt init' to initialize the project.\n");
    exit(1);
}

$config = [];
if (file_exists($CONFIG_FILE)) {
    try {
        $fileContents = file_get_contents($CONFIG_FILE);
        $config = Yaml::parse($fileContents) ?: [];
    } catch (Exception $error) {
        fwrite(STDERR, "❌ Error reading config file: " . $error->getMessage() . "\n");
        exit(1);
    }
}

// Extract config values with safe navigation
$INVRT_URL = getConfig($config, 'project.url', '');
$INVRT_DEPTH_TO_CRAWL = getConfig($config, 'settings.max_crawl_depth', '');
$INVRT_MAX_PAGES = getConfig($config, 'settings.max_pages', '');
$INVRT_USER_AGENT = getConfig($config, 'settings.user_agent', '');
$INVRT_MAX_CONCURRENT_REQUESTS = getConfig($config, 'settings.max_concurrent_requests', '');
$INVRT_USERNAME = '';
$INVRT_PASSWORD = '';
$INVRT_COOKIE = '';

// Load profile-specific settings and override defaults
$profileSettings = isset($config['profiles'][$INVRT_PROFILE]) ? $config['profiles'][$INVRT_PROFILE] : null;
if ($profileSettings) {
    echo "⚙️  Loading profile settings for '" . $INVRT_PROFILE . "'\n";
    
    // Override with profile-specific settings if they exist
    if (isset($profileSettings['url'])) {
        $INVRT_URL = $profileSettings['url'];
    }
    if (isset($profileSettings['max_crawl_depth'])) {
        $INVRT_DEPTH_TO_CRAWL = $profileSettings['max_crawl_depth'];
    }
    if (isset($profileSettings['max_pages'])) {
        $INVRT_MAX_PAGES = $profileSettings['max_pages'];
    }
    if (isset($profileSettings['user_agent'])) {
        $INVRT_USER_AGENT = $profileSettings['user_agent'];
    }
    if (isset($profileSettings['max_concurrent_requests'])) {
        $INVRT_MAX_CONCURRENT_REQUESTS = $profileSettings['max_concurrent_requests'];
    }
    // Load auth credentials if provided in profile
    if (isset($profileSettings['auth']['username'])) {
        $INVRT_USERNAME = $profileSettings['auth']['username'];
    }
    if (isset($profileSettings['auth']['password'])) {
        $INVRT_PASSWORD = $profileSettings['auth']['password'];
    }
    if (isset($profileSettings['auth']['cookie'])) {
        $INVRT_COOKIE = $profileSettings['auth']['cookie'];
    }
}

// Load environment-specific settings and override defaults
if ($INVRT_ENVIRONMENT) {
    $environmentSettings = isset($config['environments'][$INVRT_ENVIRONMENT]) ? $config['environments'][$INVRT_ENVIRONMENT] : null;
    if ($environmentSettings) {
        echo "🌍 Loading environment settings for '" . $INVRT_ENVIRONMENT . "'\n";
        
        // Override with environment-specific settings if they exist
        if (isset($environmentSettings['url'])) {
            $INVRT_URL = $environmentSettings['url'];
        }
        if (isset($environmentSettings['max_crawl_depth'])) {
            $INVRT_DEPTH_TO_CRAWL = $environmentSettings['max_crawl_depth'];
        }
        if (isset($environmentSettings['max_pages'])) {
            $INVRT_MAX_PAGES = $environmentSettings['max_pages'];
        }
        if (isset($environmentSettings['user_agent'])) {
            $INVRT_USER_AGENT = $environmentSettings['user_agent'];
        }
        if (isset($environmentSettings['max_concurrent_requests'])) {
            $INVRT_MAX_CONCURRENT_REQUESTS = $environmentSettings['max_concurrent_requests'];
        }
        // Load auth credentials if provided in environment
        if (isset($environmentSettings['auth']['username'])) {
            $INVRT_USERNAME = $environmentSettings['auth']['username'];
        }
        if (isset($environmentSettings['auth']['password'])) {
            $INVRT_PASSWORD = $environmentSettings['auth']['password'];
        }
        if (isset($environmentSettings['auth']['cookie'])) {
            $INVRT_COOKIE = $environmentSettings['auth']['cookie'];
        }
    } else {
        echo "⚠️  Environment '" . $INVRT_ENVIRONMENT . "' not found in config.yaml\n";
    }
}

$INVRT_DATA_DIR = $INVRT_DIRECTORY . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $INVRT_PROFILE . DIRECTORY_SEPARATOR . $INVRT_ENVIRONMENT;
$INVRT_COOKIES_FILE = $INVRT_DATA_DIR . DIRECTORY_SEPARATOR . 'cookies.json';

// Get the scripts directory
$INVRT_SCRIPTS_DIR = __DIR__;

// Set up environment variables (in $_ENV for this process)
$_ENV = array_merge($_ENV, [
    'INVRT_SCRIPTS_DIR' => $INVRT_SCRIPTS_DIR,
    'INVRT_DIRECTORY' => $INVRT_DIRECTORY,
    'INVRT_DATA_DIR' => $INVRT_DATA_DIR,
    'INVRT_URL' => $INVRT_URL,
    'INVRT_DEPTH_TO_CRAWL' => $INVRT_DEPTH_TO_CRAWL,
    'INVRT_MAX_PAGES' => $INVRT_MAX_PAGES,
    'INVRT_USER_AGENT' => $INVRT_USER_AGENT,
    'INVRT_MAX_CONCURRENT_REQUESTS' => $INVRT_MAX_CONCURRENT_REQUESTS,
    'INVRT_PROFILE' => $INVRT_PROFILE,
    'INVRT_DEVICE' => $INVRT_DEVICE,
    'INVRT_ENVIRONMENT' => $INVRT_ENVIRONMENT,
    'INVRT_USERNAME' => $INVRT_USERNAME,
    'INVRT_PASSWORD' => $INVRT_PASSWORD,
    'INVRT_COOKIE' => $INVRT_COOKIE,
    'INVRT_COOKIES_FILE' => $INVRT_COOKIES_FILE,
]);

// Execute the command with login handling
executeCommand($command, $_ENV);

?>
