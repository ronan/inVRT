const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const yaml = require('js-yaml');
const log = require('./logger');

const {
  INVRT_PLAN_FILE,
  INVRT_PROFILE,
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

const mergePageMeta = (node, pagePath, profile, projectSeed, title) => {
  if (!Array.isArray(node.profiles)) {
    node.profiles = [];
  }
  if (!node.profiles.includes(profile)) {
    node.profiles.push(profile);
  }
  if (!node.id) {
    node.id = encodeId(pagePath, projectSeed);
  }
  if (typeof title === 'string' && title !== '' && !node.title) {
    node.title = title;
  }
};

const insertPathIntoTree = (pages, urlPath, profile, projectSeed, title) => {
  if (urlPath === '/') {
    const root = ensureObjectNode(pages['/']);
    mergePageMeta(root, '/', profile, projectSeed, title);
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
      mergePageMeta(queryNode, urlPath, profile, projectSeed, title);
      root[search] = queryNode;
      return;
    }

    mergePageMeta(root, '/', profile, projectSeed, title);
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
    mergePageMeta(queryNode, `${currentPath}${search}`, profile, projectSeed, title);
    node[search] = queryNode;
    return;
  }

  const marker = trailingSlash ? '/' : '';
  moveMetadataToLanding(node, marker);

  const landing = ensureObjectNode(node[marker]);
  mergePageMeta(landing, currentPath + (trailingSlash ? '/' : ''), profile, projectSeed, title);
  node[marker] = landing;
};

/** Strip the site title from a page title and trim non-alphanumerics from the ends. */
const cleanTitle = (rawTitle, siteTitle) => {
  if (typeof rawTitle !== 'string' || rawTitle === '') {
    return '';
  }

  let cleaned = rawTitle;
  if (siteTitle && siteTitle !== '') {
    cleaned = cleaned.split(siteTitle).join('');
  }
  cleaned = cleaned.replace(/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/gu, '').trim();

  return cleaned !== '' ? cleaned : rawTitle.trim();
};

const readStdin = () => new Promise((resolve) => {
  const chunks = [];
  process.stdin.on('data', (chunk) => chunks.push(chunk));
  process.stdin.on('end', () => resolve(Buffer.concat(chunks).toString()));
});

const run = async () => {
  if (!INVRT_PLAN_FILE) { log.error('INVRT_PLAN_FILE must be set'); process.exit(1); }
  if (!fs.existsSync(INVRT_PLAN_FILE)) {
    log.error(`plan.yaml not found at ${INVRT_PLAN_FILE}`);
    process.exit(1);
  }

  const input = await readStdin();
  const parsed = input.trim() === '' ? {} : yaml.load(input);
  const crawled = parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};

  const plan = yaml.load(fs.readFileSync(INVRT_PLAN_FILE, 'utf-8')) || {};
  if (!plan.project || typeof plan.project !== 'object') plan.project = {};
  if (!plan.pages || typeof plan.pages !== 'object') plan.pages = { '/': {} };

  const homeTitle = typeof crawled['/'] === 'string' ? crawled['/'] : '';
  const siteTitle = (typeof plan.project.title === 'string' && plan.project.title !== '')
    ? plan.project.title
    : homeTitle;

  if (homeTitle !== '' && !plan.project.title) {
    plan.project.title = homeTitle;
  }

  const profile = INVRT_PROFILE || 'anonymous';
  const projectSeed = deriveProjectSeed();

  for (const [urlPath, rawTitle] of Object.entries(crawled)) {
    const title = urlPath === '/'
      ? (typeof rawTitle === 'string' ? rawTitle.trim() : '')
      : cleanTitle(rawTitle, siteTitle);
    insertPathIntoTree(plan.pages, urlPath, profile, projectSeed, title);
  }

  fs.mkdirSync(path.dirname(INVRT_PLAN_FILE), { recursive: true });
  fs.writeFileSync(INVRT_PLAN_FILE, yaml.dump(plan, { lineWidth: -1 }));
  log.info(`Updated plan.yaml with ${Object.keys(crawled).length} crawled pages.`);
};

run().catch((err) => {
  log.error(err.message || String(err));
  process.exit(1);
});
