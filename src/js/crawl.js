const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const { generateBackstopConfig } = require('./backstop-config');
const log = require('./logger');

const {
  INVRT_URL,
  INVRT_CRAWL_FILE,
  INVRT_CRAWL_LOG,
  INVRT_CRAWL_DIR,
  INVRT_CLONE_DIR,
  INVRT_MAX_CRAWL_DEPTH,
  INVRT_MAX_PAGES,
  INVRT_EXCLUDE_FILE,
  INVRT_COOKIE,
  INVRT_COOKIES_FILE,
  INVRT_PROFILE,
  INVRT_ENVIRONMENT,
  INVRT_CHECK_FILE,
} = process.env;

/** Build wget --exclude-directories arg from exclude file or defaults. */
const resolveExcludeArg = () => {
  if (!INVRT_EXCLUDE_FILE || !fs.existsSync(INVRT_EXCLUDE_FILE)) {
    const defaults = '/user/*';
    log.info(`No exclude file found at ${INVRT_EXCLUDE_FILE}. Excluding defaults: ${defaults}`);
    return `--exclude-directories=${defaults}`;
  }

  const lines = fs.readFileSync(INVRT_EXCLUDE_FILE, 'utf-8')
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter((l) => l && !l.startsWith('#'));
  const excludeUrls = lines.join(',');
  log.info(`Excluding URLs: ${excludeUrls}`);
  return `--exclude-directories=${excludeUrls}`;
};

/** Build wget cookie arg from raw cookie header or cookie file. */
const resolveCookieArg = () => {
  if (INVRT_COOKIE) {
    log.info('Using provided cookie for crawling.');
    return `--header=Cookie: ${INVRT_COOKIE}`;
  }

  const cookieTxt = `${INVRT_COOKIES_FILE}.txt`;
  if (INVRT_COOKIES_FILE && fs.existsSync(cookieTxt)) {
    log.info(`Using cookies from file: ${cookieTxt}`);
    return `--load-cookies=${cookieTxt}`;
  }

  log.info('No cookie provided. Crawling without authentication.');
  if (INVRT_COOKIES_FILE) {
    try { fs.closeSync(fs.openSync(`${INVRT_COOKIES_FILE}.txt`, 'a')); } catch { /* ignore */ }
  }
  return null;
};

/** Parse crawl log and return sorted unique paths under baseUrl. */
const parseUrlsFromLog = (logFile, baseUrl) => {
  if (!fs.existsSync(logFile)) return [];

  const marker = `URL:${baseUrl}`;
  const paths = new Set();

  fs.readFileSync(logFile, 'utf-8')
    .split(/\r?\n/)
    .filter((line) => line.includes(marker))
    .forEach((line) => {
      const rest = line.slice(line.indexOf(marker) + marker.length);
      const p = rest.split(/[ \t]/)[0];
      if (p) paths.add(p);
    });

  return [...paths].sort();
};

const prepareDir = (dir) => {
  if (dir) fs.mkdirSync(dir, { recursive: true });
};

const run = () => {
  if (!INVRT_URL) { log.error('INVRT_URL must be set'); process.exit(1); }
  if (!INVRT_CRAWL_DIR) { log.error('INVRT_CRAWL_DIR must be set'); process.exit(1); }

  const maxDepth = INVRT_MAX_CRAWL_DEPTH || 3;
  const maxPages = INVRT_MAX_PAGES || 100;

  log.info(`🕸️ Crawling '${INVRT_ENVIRONMENT}' environment (${INVRT_URL}) with profile: '${INVRT_PROFILE}' to depth: ${maxDepth}, max pages: ${maxPages}`);

  // Prepare directories first
  [INVRT_CRAWL_DIR, INVRT_CLONE_DIR, INVRT_CRAWL_LOG ? path.dirname(INVRT_CRAWL_LOG) : null]
    .forEach(prepareDir);

  // Clear previous results
  if (INVRT_CRAWL_LOG) fs.writeFileSync(INVRT_CRAWL_LOG, '');
  if (INVRT_CRAWL_FILE && fs.existsSync(INVRT_CRAWL_FILE)) fs.unlinkSync(INVRT_CRAWL_FILE);

  // Run check if not yet done
  if (INVRT_CHECK_FILE && !fs.existsSync(INVRT_CHECK_FILE)) {
    log.info('🔍 No site check found — running check first.');
    const checkResult = spawnSync(process.execPath, [path.join(__dirname, 'check.js')], {
      env: process.env,
      stdio: 'inherit',
    });
    if (checkResult.status !== 0) {
      log.warn('Site check failed. Continuing with crawl.');
    }
  }

  const host = new URL(INVRT_URL).hostname;
  const excludeArg = resolveExcludeArg();
  const cookieArg = resolveCookieArg();

  const wgetArgs = [
    excludeArg,
    cookieArg,
    `--level=${maxDepth}`,
    `--domains=${host}`,
    `--directory-prefix=${INVRT_CLONE_DIR}`,
    '--recursive',
    '--max-redirect=3',
    '--user-agent=invrt/crawler',
    '--ignore-length',
    '--no-verbose',
    '--no-check-certificate',
    '--reject=css,js,woff,jpg,png,gif,svg,ico,pdf,ppt,pptx,doc,docx,xls,xlsx',
    '--reject-regex=(edit|devel|delete|logout|webform|files|file|login|register)',
    '--no-host-directories',
    '--execute', 'robots=off',
    INVRT_URL,
  ].filter(Boolean);

  log.debug(`Running command:\n wget ${wgetArgs.join('\\\n  ')}`);

  // wget writes its log to stderr; redirect to crawl log file
  const wgetResult = spawnSync('wget', wgetArgs, {
    env: process.env,
    stdio: ['ignore', 'pipe', INVRT_CRAWL_LOG ? fs.openSync(INVRT_CRAWL_LOG, 'a') : 'pipe'],
  });

  if (wgetResult.status !== 0) {
    log.warn(`There were errors during the crawl. See logs at ${INVRT_CRAWL_LOG}`);
    log.warn(`Crawl exit code: ${wgetResult.status}`);
  }

  const paths = parseUrlsFromLog(INVRT_CRAWL_LOG, INVRT_URL);
  const count = paths.length;

  fs.writeFileSync(INVRT_CRAWL_FILE, paths.join('\n'));

  if (count === 0) {
    log.info('No usable URLs were found during crawl. See crawl log details below:');
    if (INVRT_CRAWL_LOG && fs.existsSync(INVRT_CRAWL_LOG)) {
      const lines = fs.readFileSync(INVRT_CRAWL_LOG, 'utf-8').split(/\r?\n/).filter(Boolean);
      log.info(`Last 5 lines of crawl log:\n${lines.slice(-5).join('\n')}`);
    }
    process.exit(1);
  }

  log.info(`Crawling completed. Found ${count} unique paths. Results saved to ${INVRT_CRAWL_FILE}`);

  generateBackstopConfig();

  process.exit(0);
};

run();
