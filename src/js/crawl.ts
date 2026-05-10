import fs from 'node:fs';
import path from 'node:path';
import yaml from 'js-yaml';
import { chromium } from 'playwright';

import log from './logger.js';

type PlanFile = {
  exclude?: string[];
  pages?: Record<string, unknown>;
};

type QueueEntry = {
  p: string;
  depth: number;
};

const resolveExcludeMatchers = (plan: PlanFile): string[] => {
  const fromPlan = Array.isArray(plan.exclude)
    ? plan.exclude.filter((line): line is string => typeof line === 'string' && line.trim() !== '')
    : [];

  if (fromPlan.length > 0) {
    log.info(`Excluding URLs: ${fromPlan.join(',')}`);
    return fromPlan;
  }

  const defaults = ['/user/*'];
  log.info(`No exclude list in plan.yaml. Excluding defaults: ${defaults.join(',')}`);
  return defaults;
};

const appendLog = (line: string): void => {
  const { INVRT_CRAWL_LOG } = process.env;
  if (INVRT_CRAWL_LOG) {
    fs.appendFileSync(INVRT_CRAWL_LOG, `${line}\n`);
  }
};

const normalizePath = (urlStr: string): string => {
  const parsed = new URL(urlStr);
  return `${parsed.pathname || '/'}${parsed.search || ''}`;
};

const isExcludedPath = (urlPath: string, rules: string[]): boolean =>
  rules.some((rule) => (rule.endsWith('*')
    ? urlPath.startsWith(rule.slice(0, -1))
    : urlPath === rule || urlPath.startsWith(`${rule}/`)));

const readPlan = (): PlanFile => {
  const { INVRT_PLAN_FILE } = process.env;
  if (!INVRT_PLAN_FILE || !fs.existsSync(INVRT_PLAN_FILE)) {
    return { pages: { '/': {} } };
  }

  const parsed = yaml.load(fs.readFileSync(INVRT_PLAN_FILE, 'utf8'));
  return parsed && typeof parsed === 'object' && !Array.isArray(parsed)
    ? parsed as PlanFile
    : { pages: { '/': {} } };
};

const seedPathsFromPlan = (plan: PlanFile): string[] => {
  const keys = Object.keys(plan.pages ?? {}).filter((key) => key.startsWith('/'));
  return keys.length > 0 ? keys : ['/'];
};

const crawl = async (): Promise<Record<string, string>> => {
  const maxDepth = Number.parseInt(process.env.INVRT_MAX_CRAWL_DEPTH || '3', 10);
  const maxPages = Number.parseInt(process.env.INVRT_MAX_PAGES || '100', 10);
  const baseUrl = process.env.INVRT_URL;

  if (!baseUrl) {
    throw new Error('INVRT_URL must be set');
  }

  const plan = readPlan();
  const excludes = resolveExcludeMatchers(plan);
  const seedPaths = seedPathsFromPlan(plan);
  const origin = new URL(baseUrl).origin;
  const discovered = new Map<string, string>();
  const visited = new Set<string>();
  const queue: QueueEntry[] = seedPaths.map((entry) => ({ p: entry, depth: 0 }));

  const browser = await chromium.launch();
  const context = await browser.newContext({
    userAgent: process.env.INVRT_USER_AGENT || 'InVRT/1.0',
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  try {
    while (queue.length > 0 && discovered.size < maxPages) {
      const current = queue.shift();
      if (!current) {
        break;
      }

      const absolute = new URL(current.p, `${origin}/`).href;
      const normalizedPath = normalizePath(absolute);

      if (visited.has(normalizedPath) || isExcludedPath(normalizedPath, excludes)) {
        continue;
      }

      visited.add(normalizedPath);
      appendLog(`VISIT ${normalizedPath} depth=${current.depth}`);

      let response;
      try {
        response = await page.goto(absolute, { waitUntil: 'domcontentloaded', timeout: 15000 });
      } catch (error) {
        appendLog(`ERROR ${normalizedPath} ${(error as Error).message || String(error)}`);
        continue;
      }

      const headers = response?.headers() ?? {};
      const contentType = `${headers['content-type'] || ''}`.toLowerCase();
      if (!contentType.includes('text/html')) {
        appendLog(`SKIP-NON-HTML ${normalizedPath} ${contentType}`);
        continue;
      }

      discovered.set(normalizedPath, await page.title() || '');

      if (current.depth >= maxDepth) {
        continue;
      }

      const links = await page.$$eval('a[href]', (anchors) =>
        anchors
          .map((anchor) => anchor.getAttribute('href'))
          .filter((value): value is string => Boolean(value)));

      for (const rawHref of links) {
        if (
          rawHref.startsWith('#')
          || rawHref.startsWith('mailto:')
          || rawHref.startsWith('javascript:')
          || rawHref.startsWith('tel:')
        ) {
          continue;
        }

        let target: URL;
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

        queue.push({ p: nextPath, depth: current.depth + 1 });
      }
    }
  } finally {
    await page.close();
    await context.close();
    await browser.close();
  }

  return [...discovered.entries()]
    .sort(([left], [right]) => left.localeCompare(right))
    .reduce<Record<string, string>>((acc, [key, value]) => {
      acc[key] = value;
      return acc;
    }, {});
};

const run = async (): Promise<void> => {
  const {
    INVRT_URL,
    INVRT_CRAWL_DIR,
    INVRT_CRAWL_LOG,
    INVRT_MAX_CRAWL_DEPTH,
    INVRT_MAX_PAGES,
    INVRT_PROFILE,
    INVRT_ENVIRONMENT,
  } = process.env;

  if (!INVRT_URL) {
    log.error('INVRT_URL must be set');
    process.exit(1);
  }
  if (!INVRT_CRAWL_DIR) {
    log.error('INVRT_CRAWL_DIR must be set');
    process.exit(1);
  }

  log.info(`🕸️ Crawling '${INVRT_ENVIRONMENT}' environment (${INVRT_URL}) with profile: '${INVRT_PROFILE}' to depth: ${INVRT_MAX_CRAWL_DEPTH || 3}, max pages: ${INVRT_MAX_PAGES || 100}`);

  [INVRT_CRAWL_DIR, INVRT_CRAWL_LOG ? path.dirname(INVRT_CRAWL_LOG) : null]
    .filter((dir): dir is string => Boolean(dir))
    .forEach((dir) => fs.mkdirSync(dir, { recursive: true }));

  if (INVRT_CRAWL_LOG) {
    fs.writeFileSync(INVRT_CRAWL_LOG, '');
  }

  const pages = await crawl();
  const count = Object.keys(pages).length;

  if (count === 0) {
    log.info('No usable URLs were found during crawl. See crawl log details below:');
    if (INVRT_CRAWL_LOG && fs.existsSync(INVRT_CRAWL_LOG)) {
      const lines = fs.readFileSync(INVRT_CRAWL_LOG, 'utf8').split(/\r?\n/u).filter(Boolean);
      log.info(`Last 5 lines of crawl log:\n${lines.slice(-5).join('\n')}`);
    }
    process.exit(1);
    return;
  }

  process.stdout.write(yaml.dump(pages, { lineWidth: -1 }));
  log.info(`Crawling completed. Found ${count} unique paths.`);
};

run().catch((error) => {
  const message = (error as Error).message || String(error);
  log.error(message);
  appendLog(`FATAL ${message}`);
  process.exit(1);
});
