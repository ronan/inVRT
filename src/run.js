const profile = process.env.INVRT_PROFILE || 'default';
const device = process.env.INVRT_DEVICE || 'desktop';
const invrt_dir = process.env.INIT_CWD + '/.invrt';
const data_dir = invrt_dir + '/data/' + profile + '/' + device;

const config = {
  "viewports": [
    {
      "label": "desktop",
      "width": 1920,
      "height": 1080
    },
    {
      "label": "mobile",
      "width": 360,
      "height": 800
    }
  ],
  "scenarios": [],
  "paths": {
    "engine_scripts":     invrt_dir + "/scripts",
    "html_report":        data_dir + "/reports",
    "ci_report":          data_dir + "/reports/ci",
    "bitmaps_reference":  data_dir + "/bitmaps/reference",
    "bitmaps_test":       data_dir + "/bitmaps/test"
  },
  "report": ["browser","json"],
  "engine": "playwright",
  // "onReadyScript": "onReady.js",
  "engineOptions": {
    "browser": "chromium"
  },
  "misMatchThreshold": 0.5,
  "asyncCaptureLimit": 1,
  "asyncCompareLimit": 10,
  "resembleOutputOptions": {
    "ignoreAntialiasing": true,
    "usePreciseMatching": true
  },
  "debug": false,
  "debugWindow": false,
  "scenarioLogsInReports": true
}

try {
  const yaml = require('js-yaml');
  const fs = require('fs');
  const backstop = require('backstopjs');

  const project_config = yaml.load(fs.readFileSync(invrt_dir + '/config.yaml', 'utf8'));
  // const config = {...default_config, ...project_config};
  const base_url = project_config.project.url;

  fs
    .readFileSync(invrt_dir + "/crawled_urls.txt", 'utf-8')
    .split(/\n/)
    .forEach((url) => {
                config.scenarios.push(
                  {
                    "label":          url,
                    "url":            `${base_url}${url}`,
                  }
                );
              });
  
  const op = process.argv[2] || 'test';
  backstop(op, {config: config}).then(() => {
      console.log('Test complete')
    }).catch((err) => {
      console.log(err);
    });
} catch (err) {
  console.log(err);
}
