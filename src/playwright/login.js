const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

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
    usernameSelector = 'input[type="email"], input[name="username"]',
    passwordSelector = 'input[type="password"]',
    submitSelector = 'button[type="submit"]',
    waitForSelector = null,
    timeout = 30000,
  } = options;

  let browser;
  try {
    // Launch browser
    console.log('Launching browser...');
    browser = await chromium.launch({ headless: true });

    // Create context to preserve cookies
    const context = await browser.newContext();
    const page = await context.newPage();

    // Navigate to login page
    console.log(`Navigating to ${loginUrl}...`);
    await page.goto(loginUrl, { waitUntil: 'networkidle', timeout });

    // Fill in username
    console.log('Entering username...');
    await page.fill(usernameSelector, username, { timeout });

    // Fill in password
    console.log('Entering password...');
    await page.fill(passwordSelector, password, { timeout });

    // Submit form
    console.log('Submitting login form...');
    await page.click(submitSelector, { timeout });

    // Wait for navigation or specific element
    if (waitForSelector) {
      console.log(`Waiting for selector: ${waitForSelector}...`);
      await page.waitForSelector(waitForSelector, { timeout });
    } else {
      // Wait for network to be idle after login
      await page.waitForLoadState('networkidle', { timeout });
    }

    console.log('Login successful!');

    // Get all cookies
    const cookies = await context.cookies();

    // Create output directory if it doesn't exist
    const dir = path.dirname(outputFile);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }

    // Save cookies to JSON file
    fs.writeFileSync(outputFile, JSON.stringify(cookies, null, 2));
    console.log(`Cookies saved to ${outputFile}`);
    console.log(`Total cookies: ${cookies.length}`);

    // Close browser
    await browser.close();
    
    return cookies;
  } catch (error) {
    console.error('Error during login:', error.message);
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
      'https://example.com/login', // Replace with actual login URL
      'your-username@example.com',  // Replace with username
      'your-password',               // Replace with password
      './cookies.json',              // Output file path
      {
        usernameSelector: 'input[type="email"]',
        passwordSelector: 'input[type="password"]',
        submitSelector: 'button[type="submit"]',
        waitForSelector: '.dashboard', // Wait for dashboard element after login
        timeout: 30000,
      }
    );

    console.log('\nCookies:');
    console.log(JSON.stringify(cookies, null, 2));
  } catch (error) {
    process.exit(1);
  }
}

// Run if executed directly
if (require.main === module) {
  main();
}

module.exports = { loginAndSaveCookies };
