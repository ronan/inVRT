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
  INVRT_COOKIES_FILE
} = process.env;


const op = process.argv[2] || 'test';

console.log(`🎯 Using profile: ${INVRT_PROFILE}, device: ${INVRT_DEVICE}${INVRT_ENVIRONMENT ? `, environment: ${INVRT_ENVIRONMENT}` : ''}`);
console.log(`📂 Data directory: ${INVRT_CAPTURE_DIR}. Operation: ${op}`);

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
    "engine_scripts":     INVRT_SCRIPTS_DIR,
    "html_report":        INVRT_CAPTURE_DIR + "/reports/html",
    "ci_report":          INVRT_CAPTURE_DIR + "/reports/ci",
    "json_report":        INVRT_CAPTURE_DIR + "/reports/json",
    "bitmaps_reference":  INVRT_CAPTURE_DIR + "/bitmaps/reference",
    "bitmaps_test":       INVRT_CAPTURE_DIR + "/bitmaps/test"
  },
  // "fileNameTemplate": '{scenarioIndex}_{selectorIndex}_{selectorLabel}_{viewportLabel}',
  "report": ["json"],
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
    .forEach((url) => {
                config.scenarios.push(
                  {
                    "label":          url,
                    "url":            `${INVRT_URL}${url}`,
                    "cookiePath":     INVRT_COOKIES_FILE
                  }
                );
              });

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
