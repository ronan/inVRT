import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import yaml from 'js-yaml';
import log from './logger.js';
import { encodeId } from './lib/encode-id.js';
import { readStdin } from './lib/stdio.js';
const URL_PARSE_BASE = 'http://invrt.local/';
const isRecord = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);
const deriveProjectSeed = () => {
    const { INVRT_ID } = process.env;
    if (!INVRT_ID) {
        return 0;
    }
    return Number.parseInt(crypto.createHash('sha1').update(INVRT_ID).digest('hex').slice(0, 4), 16) & 0xffff;
};
const isChildKey = (key) => key === '.'
    || key === ''
    || key === '/'
    || key.startsWith('/')
    || key.startsWith('?');
const ensureObjectNode = (value) => {
    if (isRecord(value)) {
        return value;
    }
    if (typeof value === 'string') {
        return { title: value };
    }
    return {};
};
const moveMetadataToLanding = (node, marker) => {
    const metadataKeys = Object.keys(node).filter((key) => !isChildKey(key));
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
    const profiles = Array.isArray(node.profiles)
        ? node.profiles.filter((value) => typeof value === 'string')
        : [];
    if (!profiles.includes(profile)) {
        profiles.push(profile);
    }
    node.profiles = profiles;
    if (typeof node.id !== 'string' || node.id === '') {
        node.id = encodeId(pagePath, projectSeed);
    }
    if (title !== '' && typeof node.title !== 'string') {
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
    const parsed = new URL(urlPath, URL_PARSE_BASE);
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
const cleanTitle = (rawTitle, siteTitle) => {
    if (typeof rawTitle !== 'string' || rawTitle === '') {
        return '';
    }
    let cleaned = rawTitle;
    if (siteTitle !== '') {
        cleaned = cleaned.split(siteTitle).join('');
    }
    cleaned = cleaned.replace(/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/gu, '').trim();
    return cleaned !== '' ? cleaned : rawTitle.trim();
};
const run = async () => {
    const { INVRT_PLAN_FILE, INVRT_PROFILE } = process.env;
    if (!INVRT_PLAN_FILE) {
        log.error('INVRT_PLAN_FILE must be set');
        process.exit(1);
    }
    if (!fs.existsSync(INVRT_PLAN_FILE)) {
        log.error(`plan.yaml not found at ${INVRT_PLAN_FILE}`);
        process.exit(1);
    }
    const input = await readStdin();
    const parsed = input.trim() === '' ? {} : yaml.load(input);
    const crawled = isRecord(parsed) ? parsed : {};
    const planParsed = yaml.load(fs.readFileSync(INVRT_PLAN_FILE, 'utf8'));
    const plan = isRecord(planParsed) ? planParsed : {};
    plan.project = ensureObjectNode(plan.project);
    plan.pages = ensureObjectNode(plan.pages);
    const homeTitle = typeof crawled['/'] === 'string' ? crawled['/'] : '';
    const siteTitle = typeof plan.project.title === 'string' && plan.project.title !== ''
        ? plan.project.title
        : homeTitle;
    if (homeTitle !== '' && typeof plan.project.title !== 'string') {
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
run().catch((error) => {
    log.error(error.message || String(error));
    process.exit(1);
});
