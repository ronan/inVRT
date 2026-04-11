const yaml = require('js-yaml');
const fs = require('fs');
const backstop = require('backstopjs');
const path = require('path');

const { 
  INVRT_PROFILE, 
  INVRT_DEVICE, 
  INVRT_ENVIRONMENT,
  INVRT_SCRIPTS_DIR, 
  INVRT_CAPTURE_DIR,
  INVRT_URL, 
  INVRT_CRAWL_FILE, 
  INVRT_VIEWPORT_WIDTH, 
  INVRT_VIEWPORT_HEIGHT,
  INVRT_COOKIES_FILE,
  INVRT_MAX_PAGES
} = process.env;


const op = process.argv[2] || 'test';

console.log(`🎯 Using profile: ${INVRT_PROFILE}, device: ${INVRT_DEVICE}${INVRT_ENVIRONMENT ? `, environment: ${INVRT_ENVIRONMENT}` : ''}`);
console.log(`📂 Capture directory: ${INVRT_CAPTURE_DIR}. Operation: ${op}`);
console.log(`🍪 Cookies file: ${INVRT_COOKIES_FILE}.json`);

const builtInScriptsDir = __dirname;
const preferredScriptsDir = INVRT_SCRIPTS_DIR || builtInScriptsDir;
const requiredHookScripts = ['playwright-onbefore.js', 'playwright-onready.js'];

const hasRequiredHooks = (scriptsDir) => requiredHookScripts.every((script) => fs.existsSync(path.join(scriptsDir, script)));

const engineScriptsDir = hasRequiredHooks(preferredScriptsDir) ? preferredScriptsDir : builtInScriptsDir;

if (preferredScriptsDir !== engineScriptsDir) {
  console.log(`⚠️ Playwright hooks not found in INVRT_SCRIPTS_DIR (${preferredScriptsDir}). Falling back to built-in scripts in ${engineScriptsDir}.`);
}

console.log(`🧩 Using Playwright engine scripts from: ${engineScriptsDir}`);

const config = {
  "dynamicTestId": 'latest',
  "viewports": [
    {
      "label": INVRT_PROFILE,
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
  // "fileNameTemplate": '{scenarioIndex}',
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
}

try {
  fs
    .readFileSync(INVRT_CRAWL_FILE, 'utf-8')
    .split(/\n/)
    .slice(0, INVRT_MAX_PAGES || 100)
        .forEach((url) => {
                config.scenarios.push(
                  {
                    "label":          url,
                    "url":            `${INVRT_URL}${url}`,
                    "cookiePath":     INVRT_COOKIES_FILE + '.json'
                  }
                );
              });
  // fs.writeFileSync(path.join(INVRT_CAPTURE_DIR, 'backstop-config.json'), JSON.stringify(config, null, 2));

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
