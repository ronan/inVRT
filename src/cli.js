#!/usr/bin/env node

import path from 'path';
import { fileURLToPath } from 'url';
import yaml from 'js-yaml';
import fs from 'fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const invrtDirectory = path.join(process.env.INIT_CWD || process.cwd(), 'invrt');
console.log(`📁 INVRT_DIRECTORY: ${invrtDirectory}`);

// Read the config for the current project
const configFile = path.join(invrtDirectory, 'config.yaml');
let config = {};

try {
  const configContent = fs.readFileSync(configFile, 'utf8');
  config = yaml.load(configContent) || {};
} catch (error) {
  console.error(`Error reading config file: ${error.message}`);
}

const invrtUrl = config?.project?.url || '';
const invrtDepthToCrawl = config?.settings?.max_crawl_depth || '';
const invrtMaxPages = config?.settings?.max_pages || '';
const invrtUserAgent = config?.settings?.user_agent || '';
const invrtMaxConcurrentRequests = config?.settings?.max_concurrent_requests || '';
const invrtCrawlOutputDir = path.join(invrtDirectory, 'data', 'crawled_urls.txt');
const invrtCrawlLogDir = path.join(invrtDirectory, 'data', 'logs', 'crawl.log');
const invrtCrawlErrorLogDir = path.join(invrtDirectory, 'data', 'logs', 'crawl_error.log');

const invrtProfile = 'default';
const invrtDevice = 'desktop';

const command = process.argv[2];

const printHelp = () => {
  console.log(`
╔════════════════════════════════════════════════════════════╗
║                      INVRT CLI Help                        ║
╚════════════════════════════════════════════════════════════╝

Usage: invrt <command> [options]

Available Commands:
  init       Initialize a new INVRT project
  crawl      Crawl URLs based on configuration
  reference  Generate reference documentation
  test       Run tests on crawled data
  help       Display this help message

Examples:
  invrt init
  invrt crawl
  invrt reference
  invrt test
  invrt help

Configuration:
  Config file location: {invrtDirectory}/config.yaml
  Crawled data: {invrtDirectory}/data/crawled_urls.txt
  Logs: {invrtDirectory}/data/logs/

For more information, visit: https://github.com/ronan/invrt
  `);
};

try {
  switch (command) {
    case 'init':
      console.log('🚀 Initializing INVRT project...');
      await import('./invrt-init.js');
      break;
    case 'crawl':
      console.log('🕷️  Starting crawl process...');
      await import('./invrt-crawl.js');
      break;
    case 'reference':
      console.log('📚 Generating reference documentation...');
      await import('./invrt-reference.js');
      break;
    case 'test':
      console.log('🧪 Running tests...');
      await import('./invrt-test.js');
      break;
    case 'help':
    case '-h':
    case '--help':
      printHelp();
      break;
    case undefined:
      console.error('❌ No command provided.');
      printHelp();
      process.exit(1);
    default:
      console.error(`❌ Invalid command: "${command}"`);
      printHelp();
      process.exit(1);
  }
} catch (error) {
  console.error(`Error: ${error.message}`);
  process.exit(1);
}