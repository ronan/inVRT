import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import log from './logger.js';
import { encodeId } from './lib/encode-id.js';
import { extractPagesFromPlan } from './lib/plan-pages.js';
import { readStdin } from './lib/stdio.js';
const indentBlock = (content, spaces = 2) => content
    .split('\n')
    .map((line) => `${' '.repeat(spaces)}${line}`)
    .join('\n');
const resolveHookSource = (scriptValue, scriptsDir) => {
    if (!/\.(?:[jt]s)$/u.test(scriptValue.trim())) {
        return scriptValue;
    }
    const invrtDir = path.dirname(scriptsDir);
    let resolvedPath = scriptValue;
    if (!path.isAbsolute(resolvedPath)) {
        if (resolvedPath.startsWith('.invrt/')) {
            resolvedPath = path.resolve(path.dirname(invrtDir), resolvedPath);
        }
        else if (resolvedPath.startsWith('scripts/')) {
            resolvedPath = path.resolve(invrtDir, resolvedPath);
        }
        else {
            resolvedPath = path.resolve(scriptsDir, resolvedPath);
        }
    }
    if (!fs.existsSync(resolvedPath)) {
        throw new Error(`Script file not found: ${scriptValue}`);
    }
    return fs.readFileSync(resolvedPath, 'utf8');
};
const renderHookBlock = (scriptValue, scriptsDir) => {
    if (!scriptValue || scriptValue.trim() === '') {
        return '';
    }
    const source = resolveHookSource(scriptValue, scriptsDir);
    return `    await (async ({ page, expect }) => {\n${indentBlock(source, 8)}\n    })({ page, expect });\n`;
};
const run = async () => {
    const { INVRT_URL, INVRT_CAPTURE_DIR, INVRT_SCRIPTS_DIR, INVRT_ENVIRONMENT, INVRT_DEVICE, INVRT_SESSION_FILE, INVRT_MAX_PAGES, INVRT_ID, } = process.env;
    if (!INVRT_URL) {
        log.error('INVRT_URL must be set');
        process.exit(1);
    }
    if (!INVRT_CAPTURE_DIR) {
        log.error('INVRT_CAPTURE_DIR must be set');
        process.exit(1);
    }
    if (!INVRT_SCRIPTS_DIR) {
        log.error('INVRT_SCRIPTS_DIR must be set');
        process.exit(1);
    }
    const projectSeed = INVRT_ID
        ? Number.parseInt(crypto.createHash('sha1').update(INVRT_ID).digest('hex').slice(0, 4), 16) & 0xffff
        : 0;
    const maxPages = Number.parseInt(INVRT_MAX_PAGES || '', 10);
    const screenshotDir = `${INVRT_CAPTURE_DIR}/${INVRT_ENVIRONMENT}/${INVRT_DEVICE}`;
    const sessionFile = INVRT_SESSION_FILE && fs.existsSync(INVRT_SESSION_FILE) ? INVRT_SESSION_FILE : null;
    const relSessionFile = sessionFile ? path.relative(INVRT_SCRIPTS_DIR, sessionFile) : null;
    try {
        const input = await readStdin();
        const pages = extractPagesFromPlan(input);
        if (pages.length === 0) {
            log.error('No testable page paths found in plan.yaml');
            process.exit(1);
            return;
        }
        const scoped = Number.isFinite(maxPages) && maxPages > 0 ? pages.slice(0, maxPages) : pages;
        const storageState = relSessionFile
            ? `\ntest.use({ storageState: ${JSON.stringify(relSessionFile)} });`
            : '';
        const titleCounts = scoped.reduce((acc, entry) => {
            const title = entry.title.trim();
            if (title !== '') {
                acc.set(title, (acc.get(title) || 0) + 1);
            }
            return acc;
        }, new Map());
        const tests = scoped.map(({ path: urlPath, hooks, title, id }) => {
            const pageId = id || encodeId(urlPath, projectSeed);
            const fullUrl = `${INVRT_URL}${urlPath}`;
            const trimmedTitle = title.trim();
            const isDuplicate = trimmedTitle !== '' && (titleCounts.get(trimmedTitle) || 0) > 1;
            const testName = trimmedTitle === ''
                ? pageId
                : (isDuplicate ? `${trimmedTitle} (${pageId})` : trimmedTitle);
            return `
test(${JSON.stringify(testName)}, { tag: '@id-${pageId}' }, async ({ page }, testInfo) => {
  try {
${renderHookBlock(hooks.setup, INVRT_SCRIPTS_DIR)}    await page.goto(${JSON.stringify(fullUrl)}, { waitUntil: 'networkidle' });
${renderHookBlock(hooks.onready, INVRT_SCRIPTS_DIR)}    const screenshot = await page.screenshot({ path: testInfo.outputPath(${JSON.stringify(`${pageId}.png`)}), fullPage: true });
    expect(screenshot).toMatchSnapshot(${JSON.stringify(`${pageId}.png`)});
  } finally {
${renderHookBlock(hooks.teardown, INVRT_SCRIPTS_DIR)}  }
});`;
        }).join('\n');
        process.stdout.write(`import { test, expect } from '@playwright/test';

${storageState}
${tests}
`);
        log.info(`Generated playwright spec with ${scoped.length} tests.`);
    }
    catch (error) {
        log.error(error.message || String(error));
        process.exit(1);
    }
};
run();
