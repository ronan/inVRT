import fs from 'node:fs';
import path from 'node:path';
import yaml from 'js-yaml';
import log from './logger.js';
const isRecord = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);
const countPngs = (dir) => {
    if (dir === '' || !fs.existsSync(dir) || !fs.statSync(dir).isDirectory()) {
        return 0;
    }
    let count = 0;
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            count += countPngs(full);
        }
        else if (entry.isFile() && entry.name.toLowerCase().endsWith('.png')) {
            count += 1;
        }
    }
    return count;
};
const countPlannedPages = (pages) => {
    if (!isRecord(pages)) {
        return 0;
    }
    let count = 0;
    const walk = (node) => {
        if (typeof node === 'string' || !isRecord(node)) {
            count += 1;
            return;
        }
        const keys = Object.keys(node);
        const childKeys = keys.filter((key) => key === '' || key === '/' || key.startsWith('/') || key.startsWith('?'));
        const metaKeys = keys.filter((key) => !childKeys.includes(key));
        if (metaKeys.length > 0 || childKeys.length === 0) {
            count += 1;
        }
        for (const key of childKeys) {
            walk(node[key]);
        }
    };
    for (const [key, node] of Object.entries(pages)) {
        if (key.startsWith('/')) {
            walk(node);
        }
    }
    return count;
};
const readYaml = (file) => {
    if (!file || !fs.existsSync(file)) {
        return {};
    }
    try {
        const parsed = yaml.load(fs.readFileSync(file, 'utf8'));
        return isRecord(parsed) ? parsed : {};
    }
    catch (error) {
        log.warn(`Failed to parse ${file}`);
        return {};
    }
};
const run = () => {
    const { INVRT_PLAN_FILE, INVRT_CAPTURE_DIR, INVRT_ENVIRONMENT, INVRT_PROFILE, INVRT_DEVICE, INVRT_ID, } = process.env;
    const config = readYaml(INVRT_PLAN_FILE);
    process.stdout.write(JSON.stringify({
        name: config.project?.name || '',
        id: INVRT_ID || '',
        plan_file: INVRT_PLAN_FILE || '',
        environment: INVRT_ENVIRONMENT || '',
        profile: INVRT_PROFILE || '',
        device: INVRT_DEVICE || '',
        environments: Object.keys(config.environments || {}),
        profiles: Object.keys(config.profiles || {}),
        devices: Object.keys(config.devices || {}),
        planned_pages: countPlannedPages(config.pages),
        reference_screenshots: countPngs(`${INVRT_CAPTURE_DIR || ''}/reference/${INVRT_DEVICE || ''}`),
        test_screenshots: countPngs(`${INVRT_CAPTURE_DIR || ''}/${INVRT_ENVIRONMENT || ''}/${INVRT_DEVICE || ''}`),
    }));
};
run();
