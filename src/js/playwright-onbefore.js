const fs = require('fs');

const loadCookies = async (browserContext, scenario) => {
  let cookies = [];
  const cookiePath = scenario.cookiePath;

  // Read Cookies from File, if exists
  if (fs.existsSync(cookiePath)) {
    cookies = JSON.parse(fs.readFileSync(cookiePath));
    console.log(`🍪 Loaded ${cookies.length} cookies from ${cookiePath}`);
  }

  // Add cookies to browser
  browserContext.addCookies(cookies);
};

module.exports = async (page, scenario, viewport, isReference, browserContext) => {
  console.log(`📸 Capturing page: ${scenario.label}: ${scenario.url}`);
  await loadCookies(browserContext, scenario);
};
