const fs = require('fs');
const backstop = require('backstopjs');
const path = require('path');

const {
  INVRT_PROFILE,
  INVRT_DEVICE,
  INVRT_ENVIRONMENT,
  INVRT_CAPTURE_DIR,
  INVRT_VIEWPORT_WIDTH,
  INVRT_VIEWPORT_HEIGHT,
  INVRT_COOKIES_FILE,
  INVRT_MAX_PAGES,
  INVRT_BACKSTOP_CONFIG_FILE,
} = process.env;

const op = process.argv[2] || 'test';
const configFile = INVRT_BACKSTOP_CONFIG_FILE || path.join(INVRT_CAPTURE_DIR, 'backstop.json');

console.log(`🎯 Running '${op}'. profile: ${INVRT_PROFILE}, environment: ${INVRT_ENVIRONMENT} device: ${INVRT_DEVICE} (${INVRT_VIEWPORT_WIDTH}x${INVRT_VIEWPORT_HEIGHT})`);
console.log(`📂 Capture directory: ${INVRT_CAPTURE_DIR}`);
console.log(`📄 Max Pages: ${INVRT_MAX_PAGES}`);
console.log(`🍪 Cookies file: ${INVRT_COOKIES_FILE}.json`);
console.log(`📋 Config file: ${configFile}`);

try {
  if (!fs.existsSync(configFile)) {
    console.error(`❌ Backstop config not found at ${configFile}. Run 'invrt crawl' first.`);
    process.exit(1);
  }

  const config = JSON.parse(fs.readFileSync(configFile, 'utf-8'));

  backstop(op, {config: config}).then(() => {
      console.log('Test complete');
      process.exit(0);
    }).catch((err) => {
      console.log(err);
      process.exit(1);
    });
} catch (err) {
  console.log(err);
  process.exit(1);
}
