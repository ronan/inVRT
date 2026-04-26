const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');
const { chromium } = require('playwright');
const log = require('./logger');

const {
  INVRT_URL,
  INVRT_CRAWL_LOG,
  INVRT_CRAWL_DIR,
  INVRT_MAX_CRAWL_DEPTH,
  INVRT_MAX_PAGES,
  INVRT_PLAN_FILE,
  INVRT_USER_AGENT,
  INVRT_PROFILE,
  INVRT_ENVIRONMENT,
} = process.env;

/** Resolve excluded path matchers from plan.yaml `exclude` list, or defaults. */
const resolveExcludeMatchers = (plan) => {
  const fromPlan = Array.isArray(plan && plan.exclude) ? plan.exclude.filter((l) => typeof l === 'string' && l.trim() !== '') : [];
  if (fromPlan.length > 0) {
    log.info(`Excluding URLs: ${fromPlan.join(',')}`);
    return fromPlan;
  }
  const defaults = ['/user/*'];
  log.info(`No exclude list in plan.yaml. Excluding defaults: ${defaults.join(',')}`);
  return defaults;
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

const readPlan = () => {
  if (!INVRT_PLAN_FILE || !fs.existsSync(INVRT_PLAN_FILE)) {
    return { pages: { '/': {} } };
  }
  const parsed = yaml.load(fs.readFileSync(INVRT_PLAN_FILE, 'utf-8'));
  if (!parsed || typeof parsed !== 'object') {
    return { pages: { '/': {} } };
  }
  return parsed;
};

const seedPathsFromPlan = (plan) => {
  const keys = Object.keys(plan.pages || {}).filter((k) => typeof k === 'string' && k.startsWith('/'));
  return keys.length > 0 ? keys : ['/'];
};

const crawl = async () => {
  const maxDepth = Number.parseInt(INVRT_MAX_CRAWL_DEPTH || '3', 10);
  const maxPages = Number.parseInt(INVRT_MAX_PAGES || '100', 10);
  const plan = readPlan();
  const excludes = resolveExcludeMatchers(plan);
  const seedPaths = seedPathsFromPlan(plan);
  const base = new URL(INVRT_URL);
  const origin = base.origin;

  const discovered = new Map(); // path -> title
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

    const title = (await page.title()) || '';
    discovered.set(normalizedPath, title);

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

  return [...discovered.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .reduce((acc, [k, v]) => { acc[k] = v; return acc; }, {});
};

const run = async () => {
  if (!INVRT_URL) { log.error('INVRT_URL must be set'); process.exit(1); }
  if (!INVRT_CRAWL_DIR) { log.error('INVRT_CRAWL_DIR must be set'); process.exit(1); }

  const maxDepth = INVRT_MAX_CRAWL_DEPTH || 3;
  const maxPages = INVRT_MAX_PAGES || 100;

  log.info(`🕸️ Crawling '${INVRT_ENVIRONMENT}' environment (${INVRT_URL}) with profile: '${INVRT_PROFILE}' to depth: ${maxDepth}, max pages: ${maxPages}`);

  [INVRT_CRAWL_DIR, INVRT_CRAWL_LOG ? path.dirname(INVRT_CRAWL_LOG) : null]
    .filter(Boolean)
    .forEach((dir) => fs.mkdirSync(dir, { recursive: true }));

  if (INVRT_CRAWL_LOG) {
    fs.writeFileSync(INVRT_CRAWL_LOG, '');
  }

  const pages = await crawl();
  const count = Object.keys(pages).length;

  if (count === 0) {
    log.info('No usable URLs were found during crawl. See crawl log details below:');
    if (INVRT_CRAWL_LOG && fs.existsSync(INVRT_CRAWL_LOG)) {
      const lines = fs.readFileSync(INVRT_CRAWL_LOG, 'utf-8').split(/\r?\n/).filter(Boolean);
      log.info(`Last 5 lines of crawl log:\n${lines.slice(-5).join('\n')}`);
    }
    process.exit(1);
  }

  process.stdout.write(yaml.dump(pages, { lineWidth: -1 }));

  log.info(`Crawling completed. Found ${count} unique paths.`);

  process.exit(0);
};

run().catch((err) => {
  log.error(err.message || String(err));
  appendLog(`FATAL ${err.message || String(err)}`);
  process.exit(1);
});
