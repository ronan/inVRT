const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');
const crypto = require('crypto');
const { chromium } = require('playwright');
const log = require('./logger');

const {
  INVRT_URL,
  INVRT_CRAWL_LOG,
  INVRT_CRAWL_DIR,
  INVRT_MAX_CRAWL_DEPTH,
  INVRT_MAX_PAGES,
  INVRT_EXCLUDE_FILE,
  INVRT_PLAN_FILE,
  INVRT_USER_AGENT,
  INVRT_PROFILE,
  INVRT_ENVIRONMENT,
  INVRT_ID,
} = process.env;

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
    number /= base;
  }

  return encoded;
};

const deriveProjectSeed = () => {
  if (!INVRT_ID) {
    return 0;
  }
  return parseInt(crypto.createHash('sha1').update(INVRT_ID).digest('hex').slice(0, 4), 16) & 0xFFFF;
};

/** Resolve excluded path matchers from file or defaults. */
const resolveExcludeMatchers = () => {
  if (!INVRT_EXCLUDE_FILE || !fs.existsSync(INVRT_EXCLUDE_FILE)) {
    const defaults = ['/user/*'];
    log.info(`No exclude file found at ${INVRT_EXCLUDE_FILE}. Excluding defaults: ${defaults.join(',')}`);
    return defaults;
  }

  const lines = fs.readFileSync(INVRT_EXCLUDE_FILE, 'utf-8')
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter((l) => l && !l.startsWith('#'));
  log.info(`Excluding URLs: ${lines.join(',')}`);
  return lines;
};

const appendLog = (line) => {
  if (!INVRT_CRAWL_LOG) {
    return;
  }
  fs.appendFileSync(INVRT_CRAWL_LOG, `${line}\n`);
};

const normalizePath = (urlStr) => {
  const parsed = new URL(urlStr);
  const p = parsed.pathname || '/';
  return `${p}${parsed.search || ''}`;
};

const isExcludedPath = (urlPath, rules) => rules.some((rule) => {
  if (rule.endsWith('*')) {
    return urlPath.startsWith(rule.slice(0, -1));
  }
  return urlPath === rule || urlPath.startsWith(`${rule}/`);
});

const isChildKey = (k) => k === '' || k === '/' || k.startsWith('/') || k.startsWith('?');

const ensureObjectNode = (value) => {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    return value;
  }

  if (typeof value === 'string') {
    return { title: value };
  }

  return {};
};

const moveMetadataToLanding = (node, marker) => {
  const metadataKeys = Object.keys(node).filter((k) => !isChildKey(k));
  if (metadataKeys.length === 0) {
    return;
  }

  const landing = ensureObjectNode(node[marker]);
  for (const key of metadataKeys) {
    if (!(key in landing)) {
      landing[key] = node[key];
    }
    delete node[key];
  }
  node[marker] = landing;
};

const mergePageMeta = (node, pagePath, profile, projectSeed) => {
  if (!Array.isArray(node.profiles)) {
    node.profiles = [];
  }

  if (!node.profiles.includes(profile)) {
    node.profiles.push(profile);
  }

  if (!node.id) {
    node.id = encodeId(pagePath, projectSeed);
  }
};

const insertPathIntoTree = (pages, urlPath, profile, projectSeed) => {
  if (urlPath === '/') {
    const root = ensureObjectNode(pages['/']);
    mergePageMeta(root, '/', profile, projectSeed);
    pages['/'] = root;
    return;
  }

  const parsed = new URL(urlPath, 'http://invrt.local');
  const pathname = parsed.pathname || '/';
  const search = parsed.search || '';
  const trailingSlash = pathname.endsWith('/');
  const segments = pathname.split('/').filter(Boolean);

  if (segments.length === 0) {
    const root = ensureObjectNode(pages['/']);
    pages['/'] = root;

    if (search !== '') {
      const queryNode = ensureObjectNode(root[search]);
      mergePageMeta(queryNode, urlPath, profile, projectSeed);
      root[search] = queryNode;
      return;
    }

    mergePageMeta(root, '/', profile, projectSeed);
    return;
  }

  let container = pages;
  let currentPath = '';
  let node = null;

  for (const segment of segments) {
    const key = `/${segment}`;
    currentPath = currentPath === '' ? key : `${currentPath}${key}`;

    node = ensureObjectNode(container[key]);
    container[key] = node;
    container = node;
  }

  if (!node) {
    return;
  }

  if (search !== '') {
    const queryNode = ensureObjectNode(node[search]);
    mergePageMeta(queryNode, `${currentPath}${search}`, profile, projectSeed);
    node[search] = queryNode;
    return;
  }

  const marker = trailingSlash ? '/' : '';
  moveMetadataToLanding(node, marker);

  const landing = ensureObjectNode(node[marker]);
  mergePageMeta(landing, currentPath + (trailingSlash ? '/' : ''), profile, projectSeed);
  node[marker] = landing;
};

const readPlan = () => {
  if (!INVRT_PLAN_FILE || !fs.existsSync(INVRT_PLAN_FILE)) {
    return { project: {}, pages: { '/': {} } };
  }
  const parsed = yaml.load(fs.readFileSync(INVRT_PLAN_FILE, 'utf-8'));
  if (!parsed || typeof parsed !== 'object') {
    return { project: {}, pages: { '/': {} } };
  }
  const plan = parsed;
  if (!plan.project || typeof plan.project !== 'object') {
    plan.project = {};
  }
  if (!plan.pages || typeof plan.pages !== 'object') {
    plan.pages = { '/': {} };
  }
  return plan;
};

const writePlan = (plan) => {
  if (!INVRT_PLAN_FILE) {
    throw new Error('INVRT_PLAN_FILE must be set');
  }
  const dir = path.dirname(INVRT_PLAN_FILE);
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(INVRT_PLAN_FILE, yaml.dump(plan, { lineWidth: -1 }));
};

const seedPathsFromPlan = (plan) => {
  const keys = Object.keys(plan.pages || {}).filter((k) => typeof k === 'string' && k.startsWith('/'));
  return keys.length > 0 ? keys : ['/'];
};

const crawl = async () => {
  const maxDepth = Number.parseInt(INVRT_MAX_CRAWL_DEPTH || '3', 10);
  const maxPages = Number.parseInt(INVRT_MAX_PAGES || '100', 10);
  const excludes = resolveExcludeMatchers();
  const plan = readPlan();
  const seedPaths = seedPathsFromPlan(plan);
  const base = new URL(INVRT_URL);
  const origin = base.origin;

  const discovered = new Set();
  const visited = new Set();
  const queue = seedPaths.map((p) => ({ p, depth: 0 }));

  const browser = await chromium.launch();
  const context = await browser.newContext({
    userAgent: INVRT_USER_AGENT || 'InVRT/1.0',
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  while (queue.length > 0 && discovered.size < maxPages) {
    const { p, depth } = queue.shift();
    const absolute = new URL(p, `${origin}/`).href;
    const normalizedPath = normalizePath(absolute);

    if (visited.has(normalizedPath) || isExcludedPath(normalizedPath, excludes)) {
      continue;
    }

    visited.add(normalizedPath);
    appendLog(`VISIT ${normalizedPath} depth=${depth}`);

    let response;
    try {
      response = await page.goto(absolute, { waitUntil: 'domcontentloaded', timeout: 15000 });
    } catch (err) {
      appendLog(`ERROR ${normalizedPath} ${err.message || String(err)}`);
      continue;
    }

    const headers = response ? response.headers() : {};
    const contentType = `${headers['content-type'] || ''}`.toLowerCase();
    if (!contentType.includes('text/html')) {
      appendLog(`SKIP-NON-HTML ${normalizedPath} ${contentType}`);
      continue;
    }

    discovered.add(normalizedPath);

    const links = await page.$$eval('a[href]', (anchors) => anchors.map((a) => a.getAttribute('href')).filter(Boolean));
    if (depth >= maxDepth) {
      continue;
    }

    for (const rawHref of links) {
      if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('mailto:') || rawHref.startsWith('javascript:') || rawHref.startsWith('tel:')) {
        continue;
      }

      let target;
      try {
        target = new URL(rawHref, absolute);
      } catch {
        continue;
      }

      if (target.origin !== origin) {
        continue;
      }

      target.hash = '';
      const nextPath = normalizePath(target.href);
      if (isExcludedPath(nextPath, excludes) || visited.has(nextPath)) {
        continue;
      }

      queue.push({ p: nextPath, depth: depth + 1 });
    }
  }

  await page.close();
  await context.close();
  await browser.close();

  const projectSeed = deriveProjectSeed();

  for (const discoveredPath of discovered) {
    insertPathIntoTree(plan.pages, discoveredPath, INVRT_PROFILE || 'anonymous', projectSeed);
  }
  writePlan(plan);

  return [...discovered].sort();
};

const run = async () => {
  if (!INVRT_URL) { log.error('INVRT_URL must be set'); process.exit(1); }
  if (!INVRT_CRAWL_DIR) { log.error('INVRT_CRAWL_DIR must be set'); process.exit(1); }
  if (!INVRT_PLAN_FILE) { log.error('INVRT_PLAN_FILE must be set'); process.exit(1); }

  const maxDepth = INVRT_MAX_CRAWL_DEPTH || 3;
  const maxPages = INVRT_MAX_PAGES || 100;

  log.info(`🕸️ Crawling '${INVRT_ENVIRONMENT}' environment (${INVRT_URL}) with profile: '${INVRT_PROFILE}' to depth: ${maxDepth}, max pages: ${maxPages}`);

  [INVRT_CRAWL_DIR, INVRT_CRAWL_LOG ? path.dirname(INVRT_CRAWL_LOG) : null]
    .filter(Boolean)
    .forEach((dir) => fs.mkdirSync(dir, { recursive: true }));

  if (INVRT_CRAWL_LOG) {
    fs.writeFileSync(INVRT_CRAWL_LOG, '');
  }

  const paths = await crawl();
  const count = paths.length;

  if (count === 0) {
    log.info('No usable URLs were found during crawl. See crawl log details below:');
    if (INVRT_CRAWL_LOG && fs.existsSync(INVRT_CRAWL_LOG)) {
      const lines = fs.readFileSync(INVRT_CRAWL_LOG, 'utf-8').split(/\r?\n/).filter(Boolean);
      log.info(`Last 5 lines of crawl log:\n${lines.slice(-5).join('\n')}`);
    }
    process.exit(1);
  }

  process.stdout.write(paths.join('\n'));

  log.info(`Crawling completed. Found ${count} unique paths.`);

  process.exit(0);
};

run().catch((err) => {
  log.error(err.message || String(err));
  appendLog(`FATAL ${err.message || String(err)}`);
  process.exit(1);
});
