#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execFile } = require('child_process');
const yaml = require('js-yaml');

// Get the INVRT_DIRECTORY
const INVRT_DIRECTORY = path.join(process.env.INIT_CWD || process.cwd(), '.invrt');
console.log('📁 INVRT_DIRECTORY:', INVRT_DIRECTORY);

// Get the command from arguments
const command = process.argv[2];

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
const INVRT_URL = config.project?.url || '';
const INVRT_DEPTH_TO_CRAWL = config.settings?.max_crawl_depth || '';
const INVRT_MAX_PAGES = config.settings?.max_pages || '';
const INVRT_USER_AGENT = config.settings?.user_agent || '';
const INVRT_MAX_CONCURRENT_REQUESTS = config.settings?.max_concurrent_requests || '';
const INVRT_CRAWL_OUTPUT_DIR = path.join(INVRT_DIRECTORY, 'data', 'crawled_urls.txt');
const INVRT_CRAWL_LOG_DIR = path.join(INVRT_DIRECTORY, 'data', 'logs', 'crawl.log');
const INVRT_CRAWL_ERROR_LOG_DIR = path.join(INVRT_DIRECTORY, 'data', 'logs', 'crawl_error.log');

const INVRT_PROFILE = 'default';
const INVRT_DEVICE = 'desktop';

// Get the scripts directory
const scriptsDir = __dirname;

// Set up environment variables
const env = {
    ...process.env,
    INVRT_DIRECTORY,
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
};

// Map commands to bash scripts
const scriptMap = {
    'init': 'invrt-init.sh',
    'crawl': 'invrt-crawl.sh',
    'reference': 'invrt-reference.sh',
    'test': 'invrt-test.sh',
};

if (!command || !scriptMap[command]) {
    console.error('Invalid command. Usage: invrt {init|crawl|reference|test}');
    process.exit(1);
}

// Execute the appropriate bash script
const scriptPath = path.join(scriptsDir, scriptMap[command]);

if (!fs.existsSync(scriptPath)) {
    console.error('❌ Script not found:', scriptPath);
    process.exit(1);
}

execFile('bash', [scriptPath], { env, stdio: 'inherit' }, (error, stdout, stderr) => {
    if (error) {
        process.exit(error.code || 1);
    }
    process.exit(0);
});
