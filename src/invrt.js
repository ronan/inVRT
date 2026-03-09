#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const yaml = require('js-yaml');

// Get the INVRT_DIRECTORY
const INVRT_DIRECTORY = path.join(process.env.INIT_CWD || process.cwd(), '.invrt');
console.log('📁 INVRT_DIRECTORY:', INVRT_DIRECTORY);

// Get the command from arguments
const command = process.argv[2];

// Parse optional arguments for profile and device
let INVRT_PROFILE = 'default';
let INVRT_DEVICE = 'desktop';

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
    }
}

console.log(`📋 Profile: ${INVRT_PROFILE}, Device: ${INVRT_DEVICE}`);

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


const INVRT_DATA_DIR = path.join(INVRT_DIRECTORY, 'data', INVRT_PROFILE, INVRT_DEVICE);

const INVRT_CRAWL_OUTPUT_DIR = path.join(INVRT_DATA_DIR, 'crawled_urls.txt');
const INVRT_CRAWL_LOG_DIR = path.join(INVRT_DATA_DIR, 'logs', 'crawl.log');
const INVRT_CRAWL_ERROR_LOG_DIR = path.join(INVRT_DATA_DIR, 'logs', 'crawl_error.log');



// Get the scripts directory
const scriptsDir = __dirname;

// Set up environment variables
const env = {
    ...process.env,
    INVRT_DIRECTORY,
    INVRT_DATA_DIR,
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
    INVRT_USERNAME,
    INVRT_PASSWORD,
    INVRT_COOKIE,
};

// Map commands to bash scripts
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
  --profile, -p <name>  Set the device profile (default: default)
  --device, -d <name>   Set the device type (default: desktop)
  --help, -h           Show this help message

Examples (with npm):
  # Initialize a new project
  $ npm run init

  # Crawl a website for desktop (note the -- before options)
  $ npm run crawl -- --profile=default --device=desktop

  # Crawl a website for mobile
  $ npm run crawl -- -p mobile -d mobile

  # Create reference screenshots for testing
  $ npm run reference -- --profile=default

Examples (running directly):
  # Run with specific profile and device
  $ node src/invrt.js crawl --profile=sponsor --device=mobile

  # Short form options
  $ node src/invrt.js crawl -p sponsor -d mobile

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
