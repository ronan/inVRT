const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const {
  INVRT_PROFILE,
  INVRT_DEVICE,
  INVRT_SCRIPTS_DIR,
  INVRT_CAPTURE_DIR,
  INVRT_URL,
  INVRT_CRAWL_FILE,
  INVRT_VIEWPORT_WIDTH,
  INVRT_VIEWPORT_HEIGHT,
  INVRT_COOKIES_FILE,
  INVRT_MAX_PAGES,
  INVRT_BACKSTOP_CONFIG_FILE,
} = process.env;

const builtInScriptsDir = __dirname;
const preferredScriptsDir = INVRT_SCRIPTS_DIR || builtInScriptsDir;
const requiredHookScripts = ['playwright-onbefore.js', 'playwright-onready.js'];
const MAX_SCENARIO_LABEL_PREFIX = 40;

const normalizeLabelPart = (value) => value
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-+|-+$/g, '');

const buildScenarioLabel = (urlPath) => {
  const normalized = normalizeLabelPart(urlPath) || 'root';
  const prefix = normalized.slice(0, MAX_SCENARIO_LABEL_PREFIX);
  const hash = crypto.createHash('sha1').update(urlPath).digest('hex').slice(0, 10);

  return `${prefix}-${hash}`;
};

const hasRequiredHooks = (scriptsDir) => requiredHookScripts.every((script) => fs.existsSync(path.join(scriptsDir, script)));

const engineScriptsDir = hasRequiredHooks(preferredScriptsDir) ? preferredScriptsDir : builtInScriptsDir;

const outputFile = INVRT_BACKSTOP_CONFIG_FILE || path.join(INVRT_CAPTURE_DIR, 'backstop-config.json');

const config = {
  "dynamicTestId": 'latest',
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
  const maxPages = parseInt(INVRT_MAX_PAGES || '', 10);
  const crawlEntries = fs
    .readFileSync(INVRT_CRAWL_FILE, 'utf-8')
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);

  const scopedEntries = Number.isFinite(maxPages) && maxPages > 0
    ? crawlEntries.slice(0, maxPages)
    : crawlEntries;

  scopedEntries.forEach((urlPath) => {
    config.scenarios.push({
      "label": buildScenarioLabel(urlPath),
      "url": `${INVRT_URL}${urlPath}`,
      "cookiePath": INVRT_COOKIES_FILE + '.json',
      "invrtPath": urlPath
    });
  });

  const outputDir = path.dirname(outputFile);
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }

  fs.writeFileSync(outputFile, JSON.stringify(config, null, 2));
  console.log(`✅ Generated backstop config with ${config.scenarios.length} scenarios at ${outputFile}`);
  process.exit(0);
} catch (err) {
  console.error(err);
  process.exit(1);
}
