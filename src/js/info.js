const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');
const log = require('./logger');

const {
  INVRT_CONFIG_FILE,
  INVRT_PLAN_FILE,
  INVRT_CAPTURE_DIR,
  INVRT_ENVIRONMENT,
  INVRT_PROFILE,
  INVRT_DEVICE,
  INVRT_ID,
} = process.env;

/** Count .png files recursively in a directory. */
const countPngs = (dir) => {
  if (!dir || !fs.existsSync(dir) || !fs.statSync(dir).isDirectory()) return 0;
  let count = 0;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) count += countPngs(full);
    else if (entry.isFile() && entry.name.toLowerCase().endsWith('.png')) count++;
  }
  return count;
};

/** Count testable pages in a plan.yaml map (recursive, same shape as generate-playwright). */
const countPlannedPages = (pages) => {
  if (!pages || typeof pages !== 'object' || Array.isArray(pages)) return 0;
  let count = 0;
  const walk = (node) => {
    if (typeof node === 'string') { count++; return; }
    if (!node || typeof node !== 'object' || Array.isArray(node)) { count++; return; }
    const keys = Object.keys(node);
    const childKeys = keys.filter((k) => k === '' || k === '/' || k.startsWith('/') || k.startsWith('?'));
    const metaKeys  = keys.filter((k) => !childKeys.includes(k));
    if (metaKeys.length > 0 || childKeys.length === 0) count++;
    for (const k of childKeys) walk(node[k]);
  };
  for (const [key, node] of Object.entries(pages)) {
    if (typeof key === 'string' && key.startsWith('/')) walk(node);
  }
  return count;
};

const readYaml = (file) => {
  if (!file || !fs.existsSync(file)) return {};
  try {
    const parsed = yaml.load(fs.readFileSync(file, 'utf8'));
    return (parsed && typeof parsed === 'object') ? parsed : {};
  } catch (err) {
    log.warn({ err }, `Failed to parse ${file}`);
    return {};
  }
};

const run = () => {
  const config = readYaml(INVRT_CONFIG_FILE);
  const plan   = readYaml(INVRT_PLAN_FILE);

  const info = {
    name: (config.project && config.project.name) || '',
    id: INVRT_ID || '',
    config_file: INVRT_CONFIG_FILE || '',
    environment: INVRT_ENVIRONMENT || '',
    profile: INVRT_PROFILE || '',
    device: INVRT_DEVICE || '',
    environments: Object.keys(config.environments || {}),
    profiles: Object.keys(config.profiles || {}),
    devices: Object.keys(config.devices || {}),
    planned_pages: countPlannedPages(plan.pages),
    reference_screenshots: countPngs(`${INVRT_CAPTURE_DIR}/reference/${INVRT_DEVICE}`),
    test_screenshots: countPngs(`${INVRT_CAPTURE_DIR}/${INVRT_ENVIRONMENT}/${INVRT_DEVICE}`),
  };

  process.stdout.write(JSON.stringify(info));
};

run();
