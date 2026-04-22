const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { match } = require('assert');
const log = require('./logger');

/**
 * Logs into a web application and saves cookies to a JSON file
 * 
 * @param {string} loginUrl - The URL of the login page
 * @param {string} username - Username or email for login
 * @param {string} password - Password for login
 * @param {string} outputFile - Path to save the cookies JSON file
 * @param {Object} options - Additional options
 * @param {string} options.usernameSelector - CSS selector for username input (default: 'input[type="email"], input[name="username"]')
 * @param {string} options.passwordSelector - CSS selector for password input (default: 'input[type="password"]')
 * @param {string} options.submitSelector - CSS selector for submit button (default: 'button[type="submit"]')
 * @param {string} options.waitForSelector - Selector to wait for after login (optional)
 * @param {number} options.timeout - Timeout in milliseconds (default: 30000)
 */
async function loginAndSaveCookies(
  loginUrl,
  username,
  password,
  outputFile,
  options = {}
) {
  const {
    usernameSelector = 'input[name="username"],input[name="name"]',
    passwordSelector = '[type="password"]',
    submitSelector = '[type="submit"]',
    waitForSelector = null,
    timeout = 30000,
  } = options;

  let browser;
  try {
    // Launch browser
    log.debug('Launching browser...');
    browser = await chromium.launch({ headless: true });
    chromium.ignoreHTTPSErrors = true;

    // Create context to preserve cookies
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    const match = loginUrl.match(/\/\/(.+\.pantheonsite\.io)/);
    if (match) {
      log.debug('Adding deterrence bypass cookie to skip environment warning...');
      await context.addCookies([{
        "name": "Deterrence-Bypass",
        "value": "1",
        "domain": `.${match[1]}`,
        "path": "/",
        "httpOnly": false,
        "secure": false,
        "sameSite": "Lax"
      }]);
    }

    // Navigate to login page
    log.debug(`Navigating to ${loginUrl}...`);
    await page.goto(loginUrl, { waitUntil: 'networkidle', timeout });
    // TODO: Save the screenshot to the data direecory
    // await page.screenshot({ path: 'login-before.png' });

    // Fill in username
    log.debug('Entering username...');
    await page.fill(usernameSelector, username, { timeout });

    // Fill in password
    log.debug('Entering password...');
    await page.fill(passwordSelector, password, { timeout });

    // Submit form
    log.debug('Submitting login form...');
    await page.locator(passwordSelector).press('Enter');

    // Wait for navigation or specific element
    if (waitForSelector) {
      log.debug(`Waiting for selector: ${waitForSelector}...`);
      await page.waitForSelector(waitForSelector, { timeout });
    } else {
      // Wait for network to be idle after login
      await page.waitForLoadState('networkidle', { timeout });
    }
    // TODO: Save the screenshot to the data direecory
    // await page.screenshot({ path: 'login-after.png' });

    log.info('Login successful!');

    // Get all cookies
    const cookies = await context.cookies();

    if (!cookies || cookies.length === 0) {
      throw new Error('No cookies were retrieved after login');
    }

    // Create output directory if it doesn't exist
    jsonFile = outputFile.endsWith('.json') ? outputFile : `${outputFile}.json`;
    const dir = path.dirname(jsonFile);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }

    // Save cookies to JSON file
    fs.writeFileSync(jsonFile, JSON.stringify(cookies, null, 2));
    log.info(`Cookies saved to ${jsonFile}`);
    log.debug(`Total cookies: ${cookies.length}`);

    // Close browser
    await browser.close();

    return cookies;
  } catch (error) {
    log.error({ err: error }, error.message || String(error));
    if (browser) {
      await browser.close();
    }
    throw error;
  }
}

// Example usage
async function main() {
  try {
    const cookies = await loginAndSaveCookies(
      process.env.INVRT_LOGIN_URL,
      process.env.INVRT_USERNAME,
      process.env.INVRT_PASSWORD,
      process.env.INVRT_COOKIES_FILE,
      {
        usernameSelector: 'input[name="name"]',
        passwordSelector: 'input[type="password"]',
        submitSelector: 'input[type="submit"]',
        waitForSelector: false,
        timeout: 30000,
      }
    );

    log.debug({ count: cookies.length }, 'Cookies retrieved');
  } catch (error) {
    process.exit(1);
  }
}

// Run if executed directly
if (require.main === module) {
  main();
}

module.exports = { loginAndSaveCookies };
