const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
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

const run = async () => {
  const {
    INVRT_PROFILE,
    INVRT_DEVICE,
    INVRT_SCRIPTS_DIR,
    INVRT_CAPTURE_DIR,
    INVRT_URL,
    INVRT_VIEWPORT_WIDTH,
    INVRT_VIEWPORT_HEIGHT,
    INVRT_COOKIES_FILE,
    INVRT_MAX_PAGES,
    INVRT_ID,
  } = process.env;

  const builtInScriptsDir = __dirname;
  const preferredScriptsDir = INVRT_SCRIPTS_DIR || builtInScriptsDir;
  const requiredHookScripts = ['playwright-onbefore.js', 'playwright-onready.js'];
  const hasRequiredHooks = (dir) => requiredHookScripts.every((s) => fs.existsSync(path.join(dir, s)));
  const engineScriptsDir = hasRequiredHooks(preferredScriptsDir) ? preferredScriptsDir : builtInScriptsDir;

  // Derive a 16-bit project seed from INVRT_ID so page IDs are scoped per project.
  const projectSeed = INVRT_ID
    ? parseInt(crypto.createHash('sha1').update(INVRT_ID).digest('hex').slice(0, 4), 16) & 0xFFFF
    : 0;

  const config = {
    ...(INVRT_ID ? { id: INVRT_ID } : {}),
    "dynamicTestId": 'latest',
    "fileNameTemplate": '{scenarioLabel}',
    "viewports": [
      {
        "label": INVRT_DEVICE,
        "width": parseInt(INVRT_VIEWPORT_WIDTH, 10),
        "height": parseInt(INVRT_VIEWPORT_HEIGHT, 10)
      },
    ],
    "scenarios": [],
    "paths": {
      "engine_scripts":     engineScriptsDir,
      "html_report":        INVRT_CAPTURE_DIR + "/reports/html",
      "ci_report":          INVRT_CAPTURE_DIR + "/reports/ci",
      "json_report":        INVRT_CAPTURE_DIR + "/reports/json",
      "bitmaps_reference":  INVRT_CAPTURE_DIR + "/bitmaps/reference",
      "bitmaps_test":       INVRT_CAPTURE_DIR + "/bitmaps/test"
    },
    "report": ["html","json"],
    "engine": "playwright",
    "onReadyScript": "playwright-onready.js",
    "onBeforeScript": "playwright-onbefore.js",
    "engineOptions": {
      "browser": "chromium"
    },
    "misMatchThreshold": 0.5,
    "asyncCaptureLimit": 5,
    "asyncCompareLimit": 50,
    "resembleOutputOptions": {
      "ignoreAntialiasing": true,
      "usePreciseMatching": true
    },
    "debug": false,
    "debugWindow": false,
    "scenarioLogsInReports": true
  };

  try {
    const input = await readStdin();
    const maxPages = parseInt(INVRT_MAX_PAGES || '', 10);
    const crawlEntries = input
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean);

    const scopedEntries = Number.isFinite(maxPages) && maxPages > 0
      ? crawlEntries.slice(0, maxPages)
      : crawlEntries;

    scopedEntries.forEach((urlPath) => {
      config.scenarios.push({
        "label": encodeId(urlPath, projectSeed),
        "url": `${INVRT_URL}${urlPath}`,
        "cookiePath": INVRT_COOKIES_FILE + '.json',
        "invrtPath": urlPath
      });
    });

    process.stdout.write(JSON.stringify(config, null, 2));
    log.info(`Generated backstop config with ${config.scenarios.length} scenarios.`);
  } catch (err) {
    log.error({ err }, err.message || String(err));
    process.exit(1);
  }
};

run();
