import fs from 'node:fs';
import path from 'node:path';

import log from './logger.js';

const CONTENT = `import { defineConfig } from '@playwright/test';

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  outputDir: './results',
  preserveOutput: 'always',
  snapshotPathTemplate: 'approved/{arg}{ext}',
  reporter: [
    ['line'],
    ['json', { outputFile: 'report.json' }]
  ],
  use: {
    ignoreHTTPSErrors: true,
  },
});
`;

const run = (): void => {
  const { INVRT_PLAYWRIGHT_CONFIG_FILE } = process.env;

  if (!INVRT_PLAYWRIGHT_CONFIG_FILE) {
    log.error('INVRT_PLAYWRIGHT_CONFIG_FILE must be set');
    process.exit(1);
  }

  fs.mkdirSync(path.dirname(INVRT_PLAYWRIGHT_CONFIG_FILE), { recursive: true });
  fs.writeFileSync(INVRT_PLAYWRIGHT_CONFIG_FILE, CONTENT);
  log.info(`Wrote playwright config to ${INVRT_PLAYWRIGHT_CONFIG_FILE}`);
};

run();
