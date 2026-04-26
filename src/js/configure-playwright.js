const fs = require('fs');
const path = require('path');
const log = require('./logger');

const { INVRT_PLAYWRIGHT_CONFIG_FILE } = process.env;

const CONTENT = `import { defineConfig } from '@playwright/test';

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  outputDir: 'results',
  snapshotPathTemplate: 'reference/{arg}{ext}',
  reporter: [['html', { outputFolder: 'report' }]],
  use: {
    screenshot: 'on',
    ignoreHTTPSErrors: true,
  }
});
`;

if (!INVRT_PLAYWRIGHT_CONFIG_FILE) {
  log.error('INVRT_PLAYWRIGHT_CONFIG_FILE must be set');
  process.exit(1);
}

fs.mkdirSync(path.dirname(INVRT_PLAYWRIGHT_CONFIG_FILE), { recursive: true });
fs.writeFileSync(INVRT_PLAYWRIGHT_CONFIG_FILE, CONTENT);
log.info(`Wrote playwright config to ${INVRT_PLAYWRIGHT_CONFIG_FILE}`);
