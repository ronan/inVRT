const path = require('path');
const crypto = require('crypto');
const fs = require('fs');
const yaml = require('js-yaml');
const log = require('./logger');

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
    number = number / base;
  }
  return encoded;
};

/** Read all of stdin and return as a string. */
const readStdin = () => new Promise((resolve) => {
  const chunks = [];
  process.stdin.on('data', (chunk) => chunks.push(chunk));
  process.stdin.on('end', () => resolve(Buffer.concat(chunks).toString()));
});

const HOOK_ALIASES = {
  setup: ['setup', 'before'],
  onready: ['onready', 'ready'],
  teardown: ['teardown', 'after'],
};

const CHILD_KEY_PATTERN = /^$|^\/$|^\//;

const isChildKey = (key) => CHILD_KEY_PATTERN.test(key) || key.startsWith('?');

const indentBlock = (content, spaces = 2) => content
  .split('\n')
  .map((line) => `${' '.repeat(spaces)}${line}`)
  .join('\n');

const normalizeHookMap = (node) => {
  if (!node || typeof node !== 'object' || Array.isArray(node)) {
    return {};
  }

  return Object.fromEntries(Object.entries(HOOK_ALIASES)
    .map(([name, keys]) => [name, keys.map((key) => node[key]).find((value) => typeof value === 'string')])
    .filter(([, value]) => typeof value === 'string'));
};

const hasMetadata = (node) => Object.keys(node).some((key) => !isChildKey(key));
const hasChildren = (node) => Object.keys(node).some((key) => isChildKey(key));

/** Extract testable URL paths and inherited hooks from plan.yaml pages map. */
const extractPagesFromPlan = (content) => {
  if (!content.trim()) {
    return [];
  }

  const parsed = yaml.load(content);
  if (!parsed || typeof parsed !== 'object') {
    return [];
  }

  const pages = parsed.pages;
  if (!pages || typeof pages !== 'object' || Array.isArray(pages)) {
    return [];
  }

  const out = new Map();

  const registerPage = (pagePath, hooks) => {
    out.set(pagePath, {
      path: pagePath,
      hooks: { ...hooks },
    });
  };

  const walk = (pagePath, node, inheritedHooks = {}) => {
    if (typeof node === 'string') {
      registerPage(pagePath, inheritedHooks);
      return;
    }

    if (!node || typeof node !== 'object' || Array.isArray(node)) {
      registerPage(pagePath, inheritedHooks);
      return;
    }

    const effectiveHooks = {
      ...inheritedHooks,
      ...normalizeHookMap(node),
    };

    if (hasMetadata(node)) {
      registerPage(pagePath, effectiveHooks);
    }

    if (!hasMetadata(node) && !hasChildren(node)) {
      // Empty object shorthand means this path is testable.
      registerPage(pagePath, effectiveHooks);
    }

    for (const [key, child] of Object.entries(node)) {
      if (key === '') {
        walk(pagePath, child, effectiveHooks);
        continue;
      }

      if (key === '/') {
        const slashPath = pagePath.endsWith('/') ? pagePath : `${pagePath}/`;
        walk(slashPath, child, effectiveHooks);
        continue;
      }

      if (key.startsWith('?')) {
        walk(`${pagePath}${key}`, child, effectiveHooks);
        continue;
      }

      if (key.startsWith('/')) {
        const childPath = pagePath === '/' ? key : `${pagePath}${key}`;
        walk(childPath, child, effectiveHooks);
      }
    }
  };

  for (const [key, node] of Object.entries(pages)) {
    if (typeof key !== 'string' || !key.startsWith('/')) {
      continue;
    }
    walk(key, node);
  }

  return [...out.values()].sort((left, right) => left.path.localeCompare(right.path));
};

const resolveHookSource = (scriptValue, scriptsDir) => {
  if (typeof scriptValue !== 'string') {
    return null;
  }

  if (!/\.(?:[jt]s)$/.test(scriptValue.trim())) {
    return scriptValue;
  }

  const invrtDir = path.dirname(scriptsDir);
  let resolvedPath = scriptValue;
  if (!path.isAbsolute(resolvedPath)) {
    if (resolvedPath.startsWith('.invrt/')) {
      resolvedPath = path.resolve(path.dirname(invrtDir), resolvedPath);
    } else if (resolvedPath.startsWith('scripts/')) {
      resolvedPath = path.resolve(invrtDir, resolvedPath);
    } else {
      resolvedPath = path.resolve(scriptsDir, resolvedPath);
    }
  }

  if (!fs.existsSync(resolvedPath)) {
    throw new Error(`Script file not found: ${scriptValue}`);
  }

  return fs.readFileSync(resolvedPath, 'utf8');
};

const renderHookBlock = (label, scriptValue, scriptsDir) => {
  if (typeof scriptValue !== 'string' || scriptValue.trim() === '') {
    return '';
  }

  const source = resolveHookSource(scriptValue, scriptsDir);
  return `    await (async ({ page, expect }) => {\n${indentBlock(source, 8)}\n    })({ page, expect });\n`;
};

const run = async () => {
  const {
    INVRT_URL,
    INVRT_CAPTURE_DIR,
    INVRT_SCRIPTS_DIR,
    INVRT_ENVIRONMENT,
    INVRT_DEVICE,
    INVRT_COOKIES_FILE,
    INVRT_MAX_PAGES,
    INVRT_ID,
  } = process.env;

  if (!INVRT_URL) { log.error('INVRT_URL must be set'); process.exit(1); }
  if (!INVRT_CAPTURE_DIR) { log.error('INVRT_CAPTURE_DIR must be set'); process.exit(1); }
  if (!INVRT_SCRIPTS_DIR) { log.error('INVRT_SCRIPTS_DIR must be set'); process.exit(1); }

  const projectSeed = INVRT_ID
    ? parseInt(crypto.createHash('sha1').update(INVRT_ID).digest('hex').slice(0, 4), 16) & 0xFFFF
    : 0;

  const maxPages = parseInt(INVRT_MAX_PAGES || '', 10);
  const screenshotDir = `${INVRT_CAPTURE_DIR}/${INVRT_ENVIRONMENT}/${INVRT_DEVICE}`;
  const cookieFile = INVRT_COOKIES_FILE ? `${INVRT_COOKIES_FILE}.json` : null;
  const relScreenshotDir = path.relative(INVRT_SCRIPTS_DIR, screenshotDir);
  const relCookieFile = null; // cookieFile ? path.relative(INVRT_SCRIPTS_DIR, cookieFile) : null;

  try {
    const input = await readStdin();
    const pages = extractPagesFromPlan(input);

    if (pages.length === 0) {
      log.error('No testable page paths found in plan.yaml');
      process.exit(1);
    }

    const scoped = Number.isFinite(maxPages) && maxPages > 0 ? pages.slice(0, maxPages) : pages;

    const storageState = relCookieFile
      ? `\nuse({ storageState: ${JSON.stringify(relCookieFile)} });`
      : '';

    const tests = scoped.map(({ path: urlPath, hooks }) => {
      const id = encodeId(urlPath, projectSeed);
      const fullUrl = `${INVRT_URL}${urlPath}`;
      const setup = renderHookBlock('setup', hooks.setup, INVRT_SCRIPTS_DIR);
      const onready = renderHookBlock('onready', hooks.onready, INVRT_SCRIPTS_DIR);
      const teardown = renderHookBlock('teardown', hooks.teardown, INVRT_SCRIPTS_DIR);
      return `
test(${JSON.stringify(id)}, async ({ page }) => {
  try {
${setup}    await page.goto(${JSON.stringify(fullUrl)}, { waitUntil: 'networkidle' });
${onready}    const screenshot = await page.screenshot();
    expect(screenshot).toMatchSnapshot(${JSON.stringify(`${id}.png`)});
  } finally {
${teardown}  }
});`;
    }).join('\n');

    const spec = `import { test, expect } from '@playwright/test';

${storageState}
${tests}
`;

    process.stdout.write(spec);
    log.info(`Generated playwright spec with ${scoped.length} tests.`);
  } catch (err) {
    log.error({ err }, err.message || String(err));
    process.exit(1);
  }
};

run();
