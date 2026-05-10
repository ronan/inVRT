import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

import log from './logger.js';

type LoginOptions = {
  usernameSelector?: string;
  passwordSelector?: string;
  submitSelector?: string;
  waitForSelector?: string | false | null;
  timeout?: number;
};

export const loginAndSaveCookies = async (
  loginUrl: string,
  username: string,
  password: string,
  outputFile: string,
  options: LoginOptions = {},
): Promise<string> => {
  const {
    usernameSelector = 'input[name="username"],input[name="name"]',
    passwordSelector = '[type="password"]',
    waitForSelector = null,
    timeout = 30000,
  } = options;

  let browser: Awaited<ReturnType<typeof chromium.launch>> | undefined;

  try {
    log.debug('Launching browser...');
    browser = await chromium.launch({ headless: true });

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    const match = loginUrl.match(/\/\/(.+\.pantheonsite\.io)/u);
    if (match) {
      log.debug('Adding deterrence bypass cookie to skip environment warning...');
      await context.addCookies([{
        name: 'Deterrence-Bypass',
        value: '1',
        domain: `.${match[1]}`,
        path: '/',
        httpOnly: false,
        secure: false,
        sameSite: 'Lax',
      }]);
    }

    log.debug(`Navigating to ${loginUrl}...`);
    await page.goto(loginUrl, { waitUntil: 'networkidle', timeout });

    log.debug('Entering username...');
    await page.fill(usernameSelector, username, { timeout });

    log.debug('Entering password...');
    await page.fill(passwordSelector, password, { timeout });

    log.debug('Submitting login form...');
    await page.locator(passwordSelector).press('Enter');

    if (waitForSelector) {
      log.debug(`Waiting for selector: ${waitForSelector}...`);
      await page.waitForSelector(waitForSelector, { timeout });
    } else {
      await page.waitForLoadState('networkidle', { timeout });
    }

    log.info('Login successful!');

    const dir = path.dirname(outputFile);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }
    await context.storageState({ path: outputFile });
    log.info(`Session saved to ${outputFile}`);

    await browser.close();
    return outputFile;
  } catch (error) {
    log.error((error as Error).message || String(error));
    if (browser) {
      await browser.close();
    }
    throw error;
  }
};

const run = async (): Promise<void> => {
  try {
    await loginAndSaveCookies(
      process.env.INVRT_LOGIN_URL || '',
      process.env.INVRT_USERNAME || '',
      process.env.INVRT_PASSWORD || '',
      process.env.INVRT_SESSION_FILE || '',
      {
        usernameSelector: 'input[name="name"]',
        passwordSelector: 'input[type="password"]',
        waitForSelector: false,
        timeout: 30000,
      },
    );
  } catch {
    process.exit(1);
  }
};

run();
