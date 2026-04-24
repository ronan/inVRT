const path = require('path');
const crypto = require('crypto');
const yaml = require('js-yaml');
const log = require('./logger');

const ENCODE_ALPHABET = 'swxdyktzhgjfblrpmcqvn';

/** Stable short ID from value + seed, mirroring Runner::encodeId in PHP. */
const encodeId = (value, seed = 0) => {
  const hashHex = crypto.createHash('sha1').update(value).digest('hex').slice(0, 8);
  const hash = parseInt(hashHex, 16) >>> 0;
  let number = (BigInt(hash) << 16n) | BigInt(seed & 0xFFFF);
  const base = BigInt(ENCODE_ALPHABET.length);

  if (number === 0n) return ENCODE_ALPHABET[0];

  let encoded = '';
  while (number > 0n) {
    encoded = ENCODE_ALPHABET[Number(number % base)] + encoded;
    number = number / base;
  }
  return encoded;
};

/** Read all of stdin and return as a string. */
const readStdin = () => new Promise((resolve) => {
  const chunks = [];
  process.stdin.on('data', (chunk) => chunks.push(chunk));
  process.stdin.on('end', () => resolve(Buffer.concat(chunks).toString()));
});

/** Extract flat testable URL paths from plan.yaml pages map. */
const extractPathsFromPlan = (content) => {
  if (!content.trim()) {
    return [];
  }

  const parsed = yaml.load(content);
  if (!parsed || typeof parsed !== 'object') {
    return [];
  }

  const pages = parsed.pages;
  if (!pages || typeof pages !== 'object' || Array.isArray(pages)) {
    return [];
  }

  return Object.keys(pages)
    .filter((k) => typeof k === 'string' && k.startsWith('/'))
    .sort();
};

const run = async () => {
  const {
    INVRT_URL,
    INVRT_CAPTURE_DIR,
    INVRT_SCRIPTS_DIR,
    INVRT_ENVIRONMENT,
    INVRT_DEVICE,
    INVRT_COOKIES_FILE,
    INVRT_MAX_PAGES,
    INVRT_ID,
  } = process.env;

  if (!INVRT_URL) { log.error('INVRT_URL must be set'); process.exit(1); }
  if (!INVRT_CAPTURE_DIR) { log.error('INVRT_CAPTURE_DIR must be set'); process.exit(1); }
  if (!INVRT_SCRIPTS_DIR) { log.error('INVRT_SCRIPTS_DIR must be set'); process.exit(1); }

  const projectSeed = INVRT_ID
    ? parseInt(crypto.createHash('sha1').update(INVRT_ID).digest('hex').slice(0, 4), 16) & 0xFFFF
    : 0;

  const maxPages = parseInt(INVRT_MAX_PAGES || '', 10);
  const screenshotDir = `${INVRT_CAPTURE_DIR}/${INVRT_ENVIRONMENT}/${INVRT_DEVICE}`;
  const cookieFile = INVRT_COOKIES_FILE ? `${INVRT_COOKIES_FILE}.json` : null;
  const relScreenshotDir = path.relative(INVRT_SCRIPTS_DIR, screenshotDir);
  const relCookieFile = null; // cookieFile ? path.relative(INVRT_SCRIPTS_DIR, cookieFile) : null;

  try {
    const input = await readStdin();
    const paths = extractPathsFromPlan(input);

    if (paths.length === 0) {
      log.error('No testable page paths found in plan.yaml');
      process.exit(1);
    }

    const scoped = Number.isFinite(maxPages) && maxPages > 0 ? paths.slice(0, maxPages) : paths;

    const storageState = relCookieFile
      ? `\nuse({ storageState: ${JSON.stringify(relCookieFile)} });`
      : '';

    const tests = scoped.map((urlPath) => {
      const id = encodeId(urlPath, projectSeed);
      const fullUrl = `${INVRT_URL}${urlPath}`;
      return `
test(${JSON.stringify(id)}, async ({ page }) => {
  await page.goto(${JSON.stringify(fullUrl)}, { waitUntil: 'networkidle' });
  const screenshot = await page.screenshot();
  expect(screenshot).toMatchSnapshot(${JSON.stringify(`${id}.png`)});
});`;
    }).join('\n');

    const spec = `import { test, expect } from '@playwright/test';

${storageState}
${tests}
`;

    process.stdout.write(spec);
    log.info(`Generated playwright spec with ${scoped.length} tests.`);
  } catch (err) {
    log.error({ err }, err.message || String(err));
    process.exit(1);
  }
};

run();
