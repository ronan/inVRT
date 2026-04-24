import { defineConfig } from '@playwright/test';

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  outputDir: 'results',
  snapshotPathTemplate: 'reference/{arg}{ext}',
  reporter: [['html', { outputFolder: 'report' }]],
  use: {
    screenshot: 'on',
  }
});
