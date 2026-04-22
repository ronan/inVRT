const fs = require('fs');
const log = require('./logger');

const loadCookies = async (browserContext, scenario) => {
  let cookies = [];
  const cookiePath = scenario.cookiePath;

  // Read Cookies from File, if exists
  if (fs.existsSync(cookiePath)) {
    cookies = JSON.parse(fs.readFileSync(cookiePath));
    log.debug(`Loaded ${cookies.length} cookies from ${cookiePath}`);
  }

  // Add cookies to browser
  browserContext.addCookies(cookies);
};

module.exports = async (page, scenario, viewport, isReference, browserContext) => {
  log.debug(`Capturing page: ${scenario.label}: ${scenario.url}`);
  await loadCookies(browserContext, scenario);
};
