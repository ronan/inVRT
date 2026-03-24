<?php
// inVRT Utility Functions

use Symfony\Component\Yaml\Yaml;

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
        if (is_array($cookiesJson)) {
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
        }

        file_put_contents($txtFilePath, $netscapeFormat);
        echo "📄 Cookies converted to wget format: " . $txtFilePath . "\n";
    } catch (Exception $error) {
        fwrite(STDERR, "⚠️  Warning: Could not convert cookies to wget format: " . $error->getMessage() . "\n");
    }
}

// Function to login if credentials exist
function loginIfCredentialsExist($INVRT_USERNAME, $INVRT_PASSWORD, $INVRT_URL, $INVRT_COOKIES_FILE) {
    try {
        echo "🔐 Logging in with provided credentials...\n";
        
        include __DIR__ . '/invert-login.php';
        
        // Convert cookies to wget format
        convertCookiesForWget("$INVRT_COOKIES_FILE.json");
    } catch (Exception $error) {
        fwrite(STDERR, "❌ Login failed: " . $error->getMessage() . "\n");
        exit(1);
    }
}

// Helper function to execute any shell command with environment variables
function executeShellCmd($cmd, $env) {
    // Prepare environment variables for subprocess
    $envStr = '';
    foreach ($env as $key => $value) {
        $envStr .= $key . '=' . escapeshellarg((string)$value) . ' ';
    }

    // Execute the command
    $exitCode = null;
    passthru($envStr . $cmd, $exitCode);
    exit($exitCode ?? 0);
}

// Helper function to execute a shell script
function executeShellScript($scriptName, $env)
{
    // Execute the bash script
    $cmd = 'bash ' . escapeshellarg(joinPath(__DIR__, $scriptName));
    executeShellCmd($cmd, $env);
}

// Helper function to join file paths
function joinPath(...$segments) {
    return join(DIRECTORY_SEPARATOR, $segments);
}

function loadConfig($file)
{

    // Read the config for the current project
    $CONFIG_FILE = joinPath($_ENV['INVRT_DIRECTORY'], 'config.yaml');

    $config = [];
    try {
        $fileContents = file_get_contents($CONFIG_FILE);
        $config = Yaml::parse($fileContents) ?: [];
    } catch (Exception $error) {
        fwrite(STDERR, "❌ Error reading config file: " . $error->getMessage() . "\n");
        exit(1);
    }

    $config_keys = [
        'profile' => 'default',
        'device' => 'desktop',
        'environment' => 'local',
        'directory' => './.invrt',
        'data_dir' => 'data',
        'cookies_file' => joinPath('data', $_ENV['INVRT_PROFILE'], $_ENV['INVRT_ENVIRONMENT'], 'cookies.txt'),
        'url' => '',
        'login_url' => '',
        'max_crawl_depth' => 3,
        'max_pages' => 100,
        'user_agent' => 'InVRT/1.0',
        'max_concurrent_requests' => 5,
        'username' => '',
        'password' => '',
        'viewport_width' => 1920,
        'viewport_height' => 1080,
    ];

    // Read the config values for the current profile, device and environment
    $out = [];
    foreach (
        [
            "environments.$_ENV[INVRT_ENVIRONMENT]",
            "profiles.$_ENV[INVRT_PROFILE]",
            "devices.$_ENV[INVRT_DEVICE]",
        ] as $section
    ) {
        if (empty(getConfig($config, $section))) {
            fwrite(STDERR, "#⚠️ Section $section not found in config.yaml, using defaults\n");
        }

        foreach ($config_keys as $key => $default) {
            $env_var_name = 'INVRT_' . strtoupper($key);
            $out[$key] = $_ENV[$env_var_name] = getConfig($config, "$section.$key", $_ENV[$env_var_name] ?? $default);
        }
    }
    return $out;
}


// Helper function to safely get nested array values
function getConfig($array, $key, $default = '')
{
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
