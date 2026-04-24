import { defineConfig } from '@playwright/test';

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: '.invrt/playwright',
  snapshotPathTemplate: '.invrt/data/anonymous/bitmaps/reference/{arg}{ext}',
  reporter: 'json',
});
