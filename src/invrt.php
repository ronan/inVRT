#!/usr/bin/env php
<?php

// inVRT CLI - Visual Regression Testing Tool
// Ported from Node.js to PHP

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

// Get the INVRT_DIRECTORY
$initCwd = getenv('INIT_CWD');
$currentDir = getcwd();
$INVRT_DIRECTORY = ($initCwd ? $initCwd : $currentDir) . DIRECTORY_SEPARATOR . '.invrt';
echo "📁 INVRT_DIRECTORY: " . $INVRT_DIRECTORY . "\n";

// Get the command from arguments
$command = $argc > 1 ? $argv[1] : '';

// Parse optional arguments for profile, device, and environment
$INVRT_PROFILE = 'default';
$INVRT_DEVICE = 'desktop';
$INVRT_ENVIRONMENT = '';

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

$envDisplay = $INVRT_ENVIRONMENT ? ", Environment: " . $INVRT_ENVIRONMENT : '';
echo "📋 Profile: " . $INVRT_PROFILE . ", Device: " . $INVRT_DEVICE . $envDisplay . "\n";

// Map commands to bash scripts
$scriptMap = [
    'init' => 'invrt-init.sh',
    'crawl' => 'invrt-crawl.sh',
    'reference' => 'invrt-reference.sh',
    'test' => 'invrt-test.sh',
];

// Show help
function showHelp() {
    echo <<<'EOT'

📖 inVRT CLI - Visual Regression Testing Tool

Usage: invrt <command> [options]

Commands:
  init       Initialize a new inVRT project in the current directory
  crawl      Crawl the website and generate screenshots
  reference  Create reference screenshots for comparison
  test       Run visual regression tests
  help       Show this help message

Options:
  --profile, -p <name>      Set the device profile (default: default)
  --device, -d <name>       Set the device type (default: desktop)
  --environment, -e <name>  Set the environment (e.g., dev, staging, prod)
  --help, -h               Show this help message

Examples (with composer):
  # Initialize a new project
  $ php src/invrt.php init

  # Crawl a website for desktop
  $ php src/invrt.php crawl --profile=default --device=desktop

  # Crawl a website for mobile with environment
  $ php src/invrt.php crawl -p mobile -d mobile -e dev

  # Create reference screenshots for testing
  $ php src/invrt.php reference --profile=default --environment=staging

Examples (running directly):
  # Run with specific profile, device, and environment
  $ php src/invrt.php crawl --profile=sponsor --device=mobile --environment=prod

  # Short form options
  $ php src/invrt.php crawl -p sponsor -d mobile -e dev

EOT;
    exit(0);
}

// Check for help command or show help if no command
if (!$command || $command === 'help' || $command === '--help' || $command === '-h') {
    showHelp();
}

// Check for invalid commands
if (!isset($scriptMap[$command])) {
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

// Helper function to safely get nested array values
function getConfig($array, $key, $default = '') {
    $keys = explode('.', $key);
    $value = $array;
    foreach ($keys as $k) {
        if (is_array($value) && isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    return $value ?: $default;
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

$INVRT_CRAWL_OUTPUT_DIR = $INVRT_DATA_DIR . DIRECTORY_SEPARATOR . 'crawled_urls.txt';
$INVRT_CRAWL_LOG_DIR = $INVRT_DATA_DIR . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'crawl.log';
$INVRT_CRAWL_ERROR_LOG_DIR = $INVRT_DATA_DIR . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'crawl_error.log';
$INVRT_COOKIES_FILE = $INVRT_DATA_DIR . DIRECTORY_SEPARATOR . 'cookies.json';

// Get the scripts directory
$scriptsDir = __DIR__;

// Set up environment variables (in $_ENV for this process)
$_ENV = array_merge($_ENV, [
    'INVRT_DIRECTORY' => $INVRT_DIRECTORY,
    'INVRT_DATA_DIR' => $INVRT_DATA_DIR,
    'INVRT_SCRIPTS_DIR' => $scriptsDir,
    'INVRT_URL' => $INVRT_URL,
    'INVRT_DEPTH_TO_CRAWL' => $INVRT_DEPTH_TO_CRAWL,
    'INVRT_MAX_PAGES' => $INVRT_MAX_PAGES,
    'INVRT_USER_AGENT' => $INVRT_USER_AGENT,
    'INVRT_MAX_CONCURRENT_REQUESTS' => $INVRT_MAX_CONCURRENT_REQUESTS,
    'INVRT_CRAWL_OUTPUT_DIR' => $INVRT_CRAWL_OUTPUT_DIR,
    'INVRT_CRAWL_LOG_DIR' => $INVRT_CRAWL_LOG_DIR,
    'INVRT_CRAWL_ERROR_LOG_DIR' => $INVRT_CRAWL_ERROR_LOG_DIR,
    'INVRT_PROFILE' => $INVRT_PROFILE,
    'INVRT_DEVICE' => $INVRT_DEVICE,
    'INVRT_ENVIRONMENT' => $INVRT_ENVIRONMENT,
    'INVRT_USERNAME' => $INVRT_USERNAME,
    'INVRT_PASSWORD' => $INVRT_PASSWORD,
    'INVRT_COOKIE' => $INVRT_COOKIE,
    'INVRT_COOKIES_FILE' => $INVRT_COOKIES_FILE,
]);

// Function to convert cookies.json to wget/curl compatible Netscape format
function convertCookiesForWget($jsonFilePath) {
    try {
        if (!file_exists($jsonFilePath)) {
            echo "ℹ️  Cookies file not found. Skipping wget format conversion.\n";
            return;
        }

        $cookiesJson = json_decode(file_get_contents($jsonFilePath), true);
        $txtFilePath = str_replace('.json', '.txt', $jsonFilePath);

        // Netscape format header
        $netscapeFormat = "# Netscape HTTP Cookie File\n";
        $netscapeFormat .= "# http://curl.haxx.se/rfc/cookie_spec.html\n";
        $netscapeFormat .= "# This is a generated file!  Do not edit.\n\n";

        // Convert each cookie
        foreach ($cookiesJson as $cookie) {
            $domain = isset($cookie['domain']) ? $cookie['domain'] : '.localhost';
            $flag = (isset($cookie['secure']) && $cookie['secure']) ? 'TRUE' : 'FALSE';
            $path = isset($cookie['path']) ? $cookie['path'] : '/';
            $secure = (isset($cookie['secure']) && $cookie['secure']) ? 'TRUE' : 'FALSE';
            $expiration = isset($cookie['expires']) ? $cookie['expires'] : '0';
            $name = isset($cookie['name']) ? $cookie['name'] : '';
            $value = isset($cookie['value']) ? $cookie['value'] : '';

            $netscapeFormat .= "{$domain}\t{$flag}\t{$path}\t{$secure}\t{$expiration}\t{$name}\t{$value}\n";
        }

        file_put_contents($txtFilePath, $netscapeFormat);
        echo "📄 Cookies converted to wget format: " . $txtFilePath . "\n";
    } catch (Exception $error) {
        fwrite(STDERR, "⚠️  Warning: Could not convert cookies to wget format: " . $error->getMessage() . "\n");
    }
}

// Function to login if credentials exist (Note: PHP doesn't support async, so this is a placeholder)
function loginIfCredentialsExist($INVRT_USERNAME, $INVRT_PASSWORD, $INVRT_URL, $INVRT_COOKIES_FILE, $INVRT_DIRECTORY) {
    if (!$INVRT_USERNAME || !$INVRT_PASSWORD) {
        echo "ℹ️  No username/password found in profile. Skipping login.\n";
        return;
    }

    if (!$INVRT_URL) {
        fwrite(STDERR, "❌ Profile has credentials but no URL configured. Cannot login.\n");
        exit(1);
    }

    try {
        echo "🔐 Logging in with provided credentials...\n";
        
        // Determine login URL
        $baseUrl = rtrim($INVRT_URL, '/');
        $loginUrl = $baseUrl . "/user/login";
        
        // Ensure data directory exists
        $cookieDir = dirname($INVRT_COOKIES_FILE);
        if (!is_dir($cookieDir)) {
            mkdir($cookieDir, 0755, true);
        }

        // Note: This calls the Node.js Playwright login since PHP doesn't have native Playwright support
        // You may need to implement this differently based on your needs
        $nodeScriptPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'playwright-login.js';
        
        // If Node.js is available and we have the Playwright script, use it
        if (file_exists($nodeScriptPath)) {
            $cmd = escapeshellcmd('node ' . $nodeScriptPath);
            $output = [];
            $returnVar = 0;
            exec($cmd, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception("Playwright login failed with exit code " . $returnVar);
            }
        } else {
            echo "⚠️  Playwright login script not found. Skipping authentication.\n";
            return;
        }

        echo "✅ Login successful!\n";
        
        // Convert cookies to wget format
        convertCookiesForWget($INVRT_COOKIES_FILE);
    } catch (Exception $error) {
        fwrite(STDERR, "❌ Login failed: " . $error->getMessage() . "\n");
        exit(1);
    }
}

// Execute the command
function executeCommand($command, $scriptMap, $scriptsDir, $INVRT_USERNAME, $INVRT_PASSWORD, $INVRT_URL, $INVRT_COOKIES_FILE, $INVRT_DIRECTORY, $env) {
    // Login before executing crawl, reference, or test commands
    if (($command === 'crawl' || $command === 'reference' || $command === 'test') && ($INVRT_USERNAME || $INVRT_PASSWORD)) {
        loginIfCredentialsExist($INVRT_USERNAME, $INVRT_PASSWORD, $INVRT_URL, $INVRT_COOKIES_FILE, $INVRT_DIRECTORY);
    }

    // Execute the appropriate bash script
    $scriptPath = $scriptsDir . DIRECTORY_SEPARATOR . $scriptMap[$command];

    if (!file_exists($scriptPath)) {
        fwrite(STDERR, "❌ Script not found: " . $scriptPath . "\n");
        exit(1);
    }

    // Prepare environment variables for subprocess
    $envStr = '';
    foreach ($env as $key => $value) {
        $envStr .= escapeshellarg($key) . '=' . escapeshellarg((string)$value) . ' ';
    }

    // Execute the bash script
    $cmd = 'bash ' . escapeshellarg($scriptPath);
    $exitCode = null;
    passthru($envStr . $cmd, $exitCode);
    exit($exitCode ?? 0);
}

// Execute the command with login handling
executeCommand($command, $scriptMap, $scriptsDir, $INVRT_USERNAME, $INVRT_PASSWORD, $INVRT_URL, $INVRT_COOKIES_FILE, $INVRT_DIRECTORY, $_ENV);
?>
