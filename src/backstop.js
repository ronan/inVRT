const yaml = require('js-yaml');
const fs = require('fs');
const backstop = require('backstopjs');
const path = require('path');

const profile = process.env.INVRT_PROFILE || 'default';
const device = process.env.INVRT_DEVICE || 'desktop';
const environment = process.env.INVRT_ENVIRONMENT || '';
const invrt_dir = process.env.INVRT_DIRECTORY || (process.env.INIT_CWD + '/.invrt');
const data_dir = process.env.INVRT_DATA_DIR || (invrt_dir + '/data/' + profile + '/' + environment);
const scripts_dir = process.env.INVRT_SCRIPTS_DIR || (invrt_dir + '/scripts');
const base_url = process.env.INVRT_URL || 'http://localhost:8080';
const viewport_width = parseInt(process.env.INVRT_VIEWPORT_WIDTH) || 1920;
const viewport_height = parseInt(process.env.INVRT_VIEWPORT_HEIGHT) || 1080;
const viewport_name = process.env.INVRT_PROFILE || 'desktop';

const op = process.argv[2] || 'test';

console.log(`🎯 Using profile: ${profile}, device: ${device}${environment ? `, environment: ${environment}` : ''}`);
console.log(`📂 Data directory: ${data_dir}. Operation: ${op}`);

const config = {
  "dynamicTestId": 'latest',
  "viewports": [
    {
      "label": viewport_name,
      "width": viewport_width,
      "height": viewport_height
    },
  ],
  "scenarios": [],
  "paths": {
    "engine_scripts":     scripts_dir,
    "html_report":        data_dir + "/reports/html",
    "ci_report":          data_dir + "/reports/ci",
    "json_report":        data_dir + "/reports/json",
    "bitmaps_reference":  data_dir + "/bitmaps/reference",
    "bitmaps_test":       data_dir + "/bitmaps/test"
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
  const cookiePath = path.join(data_dir, 'cookies.json');
  fs
    .readFileSync(data_dir + "/crawled_urls.txt", 'utf-8')
    .split(/\n/)
    .forEach((url) => {
                config.scenarios.push(
                  {
                    "label":          url,
                    "url":            `${base_url}${url}`,
                    "cookiePath":     cookiePath
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
