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

const op = process.argv[2] || 'test';

console.log(`🎯 Using profile: ${profile}, device: ${device}${environment ? `, environment: ${environment}` : ''}`);
if (username) {
    console.log(`👤 Using username: ${username}`);
}
console.log(`📂 Data directory: ${data_dir}. Operation: ${op}`);

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
    "engine_scripts":     scripts_dir,
    "html_report":        data_dir + "/reports",
    "ci_report":          data_dir + "/reports/ci",
    "json_report":        data_dir + "/reports/json",
    "bitmaps_reference":  data_dir + "/bitmaps/reference",
    "bitmaps_test":       data_dir + "/bitmaps/test"
  },
  "report": ["browser","json"],
  "engine": "playwright",
  "onReadyScript": "onReady.js",
  "onBeforeScript": "playwright-onbefore.js",
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
  const project_config = yaml.load(fs.readFileSync(invrt_dir + '/config.yaml', 'utf8'));
  
  // Get base URL from project config
  let base_url = project_config.project.url;
  
  // Load profile-specific settings and override defaults
  const profileSettings = project_config.profiles?.[profile];
  if (profileSettings) {
    console.log(`⚙️  Loading profile settings for '${profile}'`);
    
    // Override URL if profile specifies one
    if (profileSettings.url) {
      base_url = profileSettings.url;
      console.log(`🔗 Using profile-specific URL: ${base_url}`);
    }
    
    // Merge profile-specific config settings into backstop config
    if (profileSettings.misMatchThreshold !== undefined) {
      config.misMatchThreshold = profileSettings.misMatchThreshold;
    }
    if (profileSettings.asyncCaptureLimit !== undefined) {
      config.asyncCaptureLimit = profileSettings.asyncCaptureLimit;
    }
    if (profileSettings.asyncCompareLimit !== undefined) {
      config.asyncCompareLimit = profileSettings.asyncCompareLimit;
    }
    if (profileSettings.engineOptions) {
      config.engineOptions = { ...config.engineOptions, ...profileSettings.engineOptions };
    }
  }

  var cookiePath = path.join(data_dir, 'cookies.json');
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
      console.log('Test complete')
    }).catch((err) => {
      console.log(err);
    });
} catch (err) {
  console.log(err);
}
