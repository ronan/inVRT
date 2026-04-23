const backstop = require('backstopjs');
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
} = process.env;

const op = process.argv[2] || 'test';

log.info(`Running backstop '${op}' — profile: ${INVRT_PROFILE}, env: ${INVRT_ENVIRONMENT}, device: ${INVRT_DEVICE} (${INVRT_VIEWPORT_WIDTH}x${INVRT_VIEWPORT_HEIGHT})`);
log.debug(`Capture directory: ${INVRT_CAPTURE_DIR}`);
log.debug(`Max pages: ${INVRT_MAX_PAGES}`);
log.debug(`Cookies file: ${INVRT_COOKIES_FILE}.json`);

const chunks = [];
process.stdin.on('data', (chunk) => chunks.push(chunk));
process.stdin.on('end', () => {
  try {
    const config = JSON.parse(Buffer.concat(chunks).toString());

    backstop(op, { config }).then(() => {
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
});
