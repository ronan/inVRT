const fs = require('fs');
const backstop = require('backstopjs');
const path = require('path');
const log = require('./logger');

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

log.info(`Running backstop '${op}' — profile: ${INVRT_PROFILE}, env: ${INVRT_ENVIRONMENT}, device: ${INVRT_DEVICE} (${INVRT_VIEWPORT_WIDTH}x${INVRT_VIEWPORT_HEIGHT})`);
log.debug(`Capture directory: ${INVRT_CAPTURE_DIR}`);
log.debug(`Max pages: ${INVRT_MAX_PAGES}`);
log.debug(`Cookies file: ${INVRT_COOKIES_FILE}.json`);
log.debug(`Config file: ${configFile}`);

try {
  if (!fs.existsSync(configFile)) {
    log.error(`Backstop config not found at ${configFile}. Run 'invrt crawl' first.`);
    process.exit(1);
  }

  const config = JSON.parse(fs.readFileSync(configFile, 'utf-8'));

  backstop(op, {config: config}).then(() => {
      log.info('Backstop run complete.');
      process.exit(0);
    }).catch((err) => {
      log.error({ err }, err.message || String(err));
      process.exit(1);
    });
} catch (err) {
  log.error({ err }, err.message || String(err));
  process.exit(1);
}
