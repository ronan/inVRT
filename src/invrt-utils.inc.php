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
function executeShellScript($scriptName, $env) {
    $INVRT_SCRIPTS_DIR = $_ENV['INVRT_SCRIPTS_DIR'] ?: "/app/src";
    
    $scriptPath = joinPath($INVRT_SCRIPTS_DIR, $scriptName);
    
    if (!file_exists($scriptPath)) {
        fwrite(STDERR, "❌ Script not found: " . $scriptPath . "\n");
        exit(1);
    }

    // Execute the bash script
    $cmd = 'bash ' . escapeshellarg($scriptPath);
    executeShellCmd($cmd, $env);
}

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
        default:
            fwrite(STDERR, "❌ Unknown command: \"" . $command . "\"\n");
            exit(1);
    }
}

// Helper function to join file paths
function joinPath(...$segments) {
    return join(DIRECTORY_SEPARATOR, $segments);
}
?>
