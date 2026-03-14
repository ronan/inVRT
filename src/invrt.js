#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const yaml = require('js-yaml');
const { loginAndSaveCookies } = require('./playwright-login');

// Get the INVRT_DIRECTORY
const INVRT_DIRECTORY = path.join(process.env.INIT_CWD || process.cwd(), '.invrt');
console.log('📁 INVRT_DIRECTORY:', INVRT_DIRECTORY);

// Get the command from arguments
const command = process.argv[2];

// Parse optional arguments for profile, device, and environment
let INVRT_PROFILE = 'default';
let INVRT_DEVICE = 'desktop';
let INVRT_ENVIRONMENT = '';

for (let i = 3; i < process.argv.length; i++) {
    const arg = process.argv[i];
    if (arg.startsWith('--profile=')) {
        INVRT_PROFILE = arg.split('=')[1];
    } else if (arg.startsWith('-p=')) {
        INVRT_PROFILE = arg.split('=')[1];
    } else if (arg === '--profile' && i + 1 < process.argv.length) {
        INVRT_PROFILE = process.argv[++i];
    } else if (arg === '-p' && i + 1 < process.argv.length) {
        INVRT_PROFILE = process.argv[++i];
    } else if (arg.startsWith('--device=')) {
        INVRT_DEVICE = arg.split('=')[1];
    } else if (arg.startsWith('-d=')) {
        INVRT_DEVICE = arg.split('=')[1];
    } else if (arg === '--device' && i + 1 < process.argv.length) {
        INVRT_DEVICE = process.argv[++i];
    } else if (arg === '-d' && i + 1 < process.argv.length) {
        INVRT_DEVICE = process.argv[++i];
    } else if (arg.startsWith('--environment=')) {
        INVRT_ENVIRONMENT = arg.split('=')[1];
    } else if (arg.startsWith('-e=')) {
        INVRT_ENVIRONMENT = arg.split('=')[1];
    } else if (arg === '--environment' && i + 1 < process.argv.length) {
        INVRT_ENVIRONMENT = process.argv[++i];
    } else if (arg === '-e' && i + 1 < process.argv.length) {
        INVRT_ENVIRONMENT = process.argv[++i];
    }
}

console.log(`📋 Profile: ${INVRT_PROFILE}, Device: ${INVRT_DEVICE}${INVRT_ENVIRONMENT ? `, Environment: ${INVRT_ENVIRONMENT}` : ''}`);

// Map commands to bash scripts (define early)
const scriptMap = {
    'init': 'invrt-init.sh',
    'crawl': 'invrt-crawl.sh',
    'reference': 'invrt-reference.sh',
    'test': 'invrt-test.sh',
};

// Show help
function showHelp() {
    console.log(`
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

Examples (with npm):
  # Initialize a new project
  $ npm run init

  # Crawl a website for desktop (note the -- before options)
  $ npm run crawl -- --profile=default --device=desktop

  # Crawl a website for mobile with environment
  $ npm run crawl -- -p mobile -d mobile -e dev

  # Create reference screenshots for testing
  $ npm run reference -- --profile=default --environment=staging

Examples (running directly):
  # Run with specific profile, device, and environment
  $ node src/invrt.js crawl --profile=sponsor --device=mobile --environment=prod

  # Short form options
  $ node src/invrt.js crawl -p sponsor -d mobile -e dev

Note: When using npm scripts, use -- before options to pass them through
`);
    process.exit(0);
}

// Check for help command or show help if no command
if (!command || command === 'help' || command === '--help' || command === '-h') {
    showHelp();
}

// Check for invalid commands
if (!scriptMap[command]) {
    console.error(`❌ Invalid command: "${command}". Use "invrt help" for usage information.`);
    process.exit(1);
}

// Read the config for the current project
const CONFIG_FILE = path.join(INVRT_DIRECTORY, 'config.yaml');

if (!fs.existsSync(CONFIG_FILE) && command !== 'init') {
    console.error('❌ Configuration file not found at', CONFIG_FILE + '. Please run \'invrt init\' to initialize the project.');
    process.exit(1);
}

let config = {};
if (fs.existsSync(CONFIG_FILE)) {
    try {
        const fileContents = fs.readFileSync(CONFIG_FILE, 'utf8');
        config = yaml.load(fileContents) || {};
    } catch (error) {
        console.error('❌ Error reading config file:', error.message);
        process.exit(1);
    }
}

// Extract config values with safe navigation
let INVRT_URL = config.project?.url || '';
let INVRT_DEPTH_TO_CRAWL = config.settings?.max_crawl_depth || '';
let INVRT_MAX_PAGES = config.settings?.max_pages || '';
let INVRT_USER_AGENT = config.settings?.user_agent || '';
let INVRT_MAX_CONCURRENT_REQUESTS = config.settings?.max_concurrent_requests || '';
let INVRT_USERNAME = '';
let INVRT_PASSWORD = '';
let INVRT_COOKIE = '';

// Load profile-specific settings and override defaults
const profileSettings = config.profiles?.[INVRT_PROFILE];
if (profileSettings) {
    console.log(`⚙️  Loading profile settings for '${INVRT_PROFILE}'`);
    
    // Override with profile-specific settings if they exist
    if (profileSettings.url) {
        INVRT_URL = profileSettings.url;
    }
    if (profileSettings.max_crawl_depth !== undefined) {
        INVRT_DEPTH_TO_CRAWL = profileSettings.max_crawl_depth;
    }
    if (profileSettings.max_pages !== undefined) {
        INVRT_MAX_PAGES = profileSettings.max_pages;
    }
    if (profileSettings.user_agent) {
        INVRT_USER_AGENT = profileSettings.user_agent;
    }
    if (profileSettings.max_concurrent_requests !== undefined) {
        INVRT_MAX_CONCURRENT_REQUESTS = profileSettings.max_concurrent_requests;
    }
    // Load auth credentials if provided in profile
    if (profileSettings.auth?.username) {
        INVRT_USERNAME = profileSettings.auth.username;
    }
    if (profileSettings.auth?.password) {
        INVRT_PASSWORD = profileSettings.auth.password;
    }
    if (profileSettings.auth?.cookie) {
        INVRT_COOKIE = profileSettings.auth.cookie;
    }
}

// Load environment-specific settings and override defaults
if (INVRT_ENVIRONMENT) {
    const environmentSettings = config.environments?.[INVRT_ENVIRONMENT];
    if (environmentSettings) {
        console.log(`🌍 Loading environment settings for '${INVRT_ENVIRONMENT}'`);
        
        // Override with environment-specific settings if they exist
        if (environmentSettings.url) {
            INVRT_URL = environmentSettings.url;
        }
        if (environmentSettings.max_crawl_depth !== undefined) {
            INVRT_DEPTH_TO_CRAWL = environmentSettings.max_crawl_depth;
        }
        if (environmentSettings.max_pages !== undefined) {
            INVRT_MAX_PAGES = environmentSettings.max_pages;
        }
        if (environmentSettings.user_agent) {
            INVRT_USER_AGENT = environmentSettings.user_agent;
        }
        if (environmentSettings.max_concurrent_requests !== undefined) {
            INVRT_MAX_CONCURRENT_REQUESTS = environmentSettings.max_concurrent_requests;
        }
        // Load auth credentials if provided in environment
        if (environmentSettings.auth?.username) {
            INVRT_USERNAME = environmentSettings.auth.username;
        }
        if (environmentSettings.auth?.password) {
            INVRT_PASSWORD = environmentSettings.auth.password;
        }
        if (environmentSettings.auth?.cookie) {
            INVRT_COOKIE = environmentSettings.auth.cookie;
        }
    } else {
        console.warn(`⚠️  Environment '${INVRT_ENVIRONMENT}' not found in config.yaml`);
    }
}

const INVRT_DATA_DIR = path.join(INVRT_DIRECTORY, 'data', INVRT_PROFILE, INVRT_ENVIRONMENT);

const INVRT_CRAWL_OUTPUT_DIR = path.join(INVRT_DATA_DIR, 'crawled_urls.txt');
const INVRT_CRAWL_LOG_DIR = path.join(INVRT_DATA_DIR, 'logs', 'crawl.log');
const INVRT_CRAWL_ERROR_LOG_DIR = path.join(INVRT_DATA_DIR, 'logs', 'crawl_error.log');
const INVRT_COOKIES_FILE = path.join(INVRT_DATA_DIR, 'cookies.json');



// Get the scripts directory
const scriptsDir = __dirname;

// Set up environment variables
const env = {
    ...process.env,
    INVRT_DIRECTORY,
    INVRT_DATA_DIR,
    INVRT_SCRIPTS_DIR: scriptsDir,
    INVRT_URL,
    INVRT_DEPTH_TO_CRAWL,
    INVRT_MAX_PAGES,
    INVRT_USER_AGENT,
    INVRT_MAX_CONCURRENT_REQUESTS,
    INVRT_CRAWL_OUTPUT_DIR,
    INVRT_CRAWL_LOG_DIR,
    INVRT_CRAWL_ERROR_LOG_DIR,
    INVRT_PROFILE,
    INVRT_DEVICE,
    INVRT_ENVIRONMENT,
    INVRT_USERNAME,
    INVRT_PASSWORD,
    INVRT_COOKIE,
    INVRT_COOKIES_FILE,
};

// Function to login if credentials exist
async function loginIfCredentialsExist() {
    if (!INVRT_USERNAME || !INVRT_PASSWORD) {
        console.log('ℹ️  No username/password found in profile. Skipping login.');
        return;
    }

    if (!INVRT_URL) {
        console.error('❌ Profile has credentials but no URL configured. Cannot login.');
        process.exit(1);
    }

    try {
        console.log('🔐 Logging in with provided credentials...');
        
        // Determine login URL (assume /login path or root)
        const baseUrl = INVRT_URL.endsWith('/') ? INVRT_URL.slice(0, -1) : INVRT_URL;
        const loginUrl = `${baseUrl}/user/login`;
        
        // Ensure data directory exists
        const cookieDir = path.dirname(INVRT_COOKIES_FILE);
        if (!fs.existsSync(cookieDir)) {
            fs.mkdirSync(cookieDir, { recursive: true });
        }

        await loginAndSaveCookies(
            loginUrl,
            INVRT_USERNAME,
            INVRT_PASSWORD,
            INVRT_COOKIES_FILE,
            {
                usernameSelector: 'input[name="name"], input[type="email"], input[name="username"], input[id="username"], input[data-testid="username"]',
                passwordSelector: 'input[name="pass"], input[type="password"]',
                submitSelector: 'button[type="submit"], input[type="submit"], button[name="submit"]',
                timeout: 60000,
            }
        );



        console.log('✅ Login successful!');
        
        // Convert cookies to wget format
        convertCookiesForWget(INVRT_COOKIES_FILE);
    } catch (error) {
        console.error('❌ Login failed:', error.message);
        process.exit(1);
    }
}

// Function to convert cookies.json to wget/curl compatible Netscape format
function convertCookiesForWget(jsonFilePath) {
    try {
        if (!fs.existsSync(jsonFilePath)) {
            console.log('ℹ️  Cookies file not found. Skipping wget format conversion.');
            return;
        }

        const cookiesJson = JSON.parse(fs.readFileSync(jsonFilePath, 'utf8'));
        const txtFilePath = jsonFilePath.replace('.json', '.txt');

        // Netscape format header
        let netscapeFormat = '# Netscape HTTP Cookie File\n';
        netscapeFormat += '# http://curl.haxx.se/rfc/cookie_spec.html\n';
        netscapeFormat += '# This is a generated file!  Do not edit.\n\n';

        // Convert each cookie
        cookiesJson.forEach(cookie => {
            const domain = cookie.domain || '.localhost';
            const flag = cookie.secure ? 'TRUE' : 'FALSE'; // Use secure flag as domain flag
            const path = cookie.path || '/';
            const secure = cookie.secure ? 'TRUE' : 'FALSE';
            const expiration = cookie.expires || '0';
            const name = cookie.name || '';
            const value = cookie.value || '';

            netscapeFormat += `${domain}\t${flag}\t${path}\t${secure}\t${expiration}\t${name}\t${value}\n`;
        });

        fs.writeFileSync(txtFilePath, netscapeFormat);
        console.log(`📄 Cookies converted to wget format: ${txtFilePath}`);
    } catch (error) {
        console.error('⚠️  Warning: Could not convert cookies to wget format:', error.message);
        // Don't exit on this error, as the main functionality (cookies.json) still works
    }
}

// Execute the command
async function executeCommand() {
    // Login before executing crawl, reference, or test commands
    if ((command === 'crawl' || command === 'reference' || command === 'test') && (INVRT_USERNAME || INVRT_PASSWORD)) {
        await loginIfCredentialsExist();
    }

    // Execute the appropriate bash script
    const scriptPath = path.join(scriptsDir, scriptMap[command]);

    if (!fs.existsSync(scriptPath)) {
        console.error('❌ Script not found:', scriptPath);
        process.exit(1);
    }

    const proc = spawn('bash', [scriptPath], { env, stdio: 'inherit' });

    proc.on('exit', (code) => {
        process.exit(code || 0);
    });

    proc.on('error', (error) => {
        console.error('❌ Error executing script:', error.message);
        process.exit(1);
    });
}

// Execute the command with login handling
executeCommand();
